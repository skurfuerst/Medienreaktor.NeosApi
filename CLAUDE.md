# Medienreaktor.NeosApi — development notes

## Two kinds of endpoint, and which one you are touching

The API is migrating from Flow controllers to attribute-annotated Api classes
served by `neos/openapi` (see the repository root's `PLAN.md`). Both exist:

- **`Classes/Features/<Feature>/<Feature>Api.php`** — a `#[Operation]` method
  per endpoint, with its DTOs in that feature's `Dto/` and its `ApiResponse`
  classes in its `Response/`. Its route, its OpenAPI operation and its
  request/response schemas are all GENERATED from the method and its DTOs, so
  there is nothing to keep in sync. Registering the class in `Settings.yaml`
  under `openApi.apiClasses` is what serves it; `Configuration/Policy.yaml`
  still needs a matcher for the method (`./flow neosapi:policycoverage` fails
  if you forget).
- **`Classes/Controller/Api/*Controller.php`** — the not-yet-migrated rest.
  Routes, policy and the hand-maintained spec move together, as below.

`Classes/Framework/` is the wiring between the two: `OpenApiRoutesProvider`
puts generated operations into Flow routing, `OpenApiDispatchController` serves
them, `SpecMerger` merges the generated document into the hand-maintained one,
and `LegacyErrorTranslator` plus `Dto\LegacyError` keep the
`{"error", "error_description"}` envelope on the wire while the library speaks
RFC 9457 internally.

**Never put an attribute on a parameter of an `#[Operation]` method.** A
`Policy.yaml` matcher makes Flow weave AOP advice, and the proxy it generates
re-declares the method without its parameters' attributes (method attributes
survive, which is why `#[Operation]` works). `#[Parameter]` is therefore
silently ignored — put the text in the operation's `description:` — and
`#[RequestBody]` / `#[AuthContext]` fail hard: the compiler treats the argument
as an ordinary parameter, throws, and since compilation happens in the routes
provider, *every* API request answers 500. See PLAN.md for the two ways out.

## The OpenAPI document is part of every endpoint change

`Resources/Private/OpenApi/openapi.yaml` is the hand-maintained contract for
the endpoints that are still controllers. Any change to
`Configuration/Routes.yaml`, a `Controller/Api` action's
parameters/body/response, a serializer's output shape, an error code, or a
required scope is INCOMPLETE until the document reflects it. Treat it like
`Configuration/Policy.yaml`: routes, policy and spec move together in the
same commit. Migrated endpoints need none of this - deleting a controller means
deleting its route and its paths from the YAML in the same step, and the
generated document takes over.

Verify before committing:

```sh
# structural validity
npx --yes @redocly/cli@latest lint Resources/Private/OpenApi/openapi.yaml

# every route documented, no phantom operations (needs PyYAML)
python3 .github/scripts/check-openapi-sync.py
```

CI (`.github/workflows/api-docs.yml`) runs both on every push/PR and
publishes the reference to GitHub Pages from `main`. Note the sync check only
covers method+path pairs — request/response schema drift is on you: when you
change what a serializer emits or what an action reads, update the matching
`components/schemas` entry.

## Serving details worth knowing

- `GET /api/openapi.json` (`DocsController::specAction`) stamps `servers`,
  the OAuth flow URLs and the scope catalog at serve time — keep the YAML
  host-agnostic.
- `GET /api/docs` renders via the Fluid template
  `Resources/Private/Templates/Api/Docs/Docs.html`. Scalar mounts
  declaratively on `data-url`/`data-configuration` — do NOT add inline
  `<script>` initialization (Fluid parses `{...}` in inline JS).
- The Scalar bundle is committed and pinned
  (`Resources/Public/Docs/scalar-api-reference-<version>.min.js`). To upgrade:
  download the new `dist/browser/standalone.min.js` from npm, commit it under
  the new versioned name, update `DocsController::SCALAR_RESOURCE` and the
  Pages workflow's `cp` glob still matches.

## General package conventions

- Policy discipline: every new `Controller\Api` action AND every new `Api\*`
  `#[Operation]` method must be matched by a privilege target in `Policy.yaml`
  (Flow treats unmatched methods as open; for controllers the Neos catch-all
  then denies them, but an Api class is not a controller, so nothing catches it
  - be explicit). `./flow neosapi:policycoverage` checks both.
- Free-form maps (dimension coordinates, node properties, workspace
  `extensions`) cannot be derived by `neos/schematic`: carry them as a builtin
  `array` property of a DTO and hand-write that DTO's `schema()`. The
  serializer preserves the string keys, so the wire format is unaffected; the
  published schema is only `additionalProperties: true` until
  `neos/jsonschema` grows the keyword. Hold every hand-written schema to
  `Neos\Schematic\Conformance::check()` — and make sure it really says
  `type: object` where a map lives: an *empty* one would otherwise encode as
  `[]`, and the schema is the only thing that says it is `{}` (which is why
  `Schematic::serialize()` takes one).
- A DTO cannot omit a member (`Serializer::readShape()` emits every constructor
  parameter). An endpoint whose response adds keys conditionally needs one
  class per shape, published as a `oneOf` and held in a builtin `array` — see
  `Features/NodeTypes/Dto/NodeTypes.php`.
- Compiling the API costs ~4 ms cold and is memoized per request
  (`CompiledApiProvider`). Measure again before adding a Flow cache.
- Load resources via the `resource://` stream wrapper, never `__DIR__`
  (Flow proxy classes resolve to the cache directory).
- Unit tests live in `Tests/Unit` and run with the distribution's suite:
  `./bin/phpunit -c Build/BuildEssentials/PhpUnit/UnitTests.xml --filter Medienreaktor`
  (the package is symlinked into `Packages/Application`, which is where the
  suite looks). Hold every hand-written DTO schema to `Conformance::check()`
  there — `Tests/Unit/Features/DtoConformanceTest.php` is the list to extend.
  Note that building a Flow `Route` emits a PHP 8.5 deprecation from Flow
  itself, and this suite fails a test that prints anything, so tests touching
  routing buffer their output.
- E2E smoke tests are shell scripts under `Tests/` and run against the local
  instance.
