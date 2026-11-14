# Response Scenarios

- Document real success statuses and complete response bodies, including the
  actual envelope and pagination metadata. Do not invent `data`, `success`,
  `message`, application codes, or an empty-array body.
- `204` means the runtime returns no content. Document `201` only when emitted.
- Cover meaningful reachable variants, including omitted/nullable nested fields.
- Trace authentication, authorization, binding, validation and domain errors;
  they may share a status but have different bodies. Inspect production error
  rendering rather than copying debug output or a neighboring controller.
- Include application error codes only when returned; follow the target's
  registry/documentation conventions if one exists.
- Unmatched routes are not controller responses. Document routing failures at
  the routing/API level when that is part of the requested contract.
- Inspect Scribe response-call configuration before relying on generated calls
  for any HTTP method. Calls may execute writes or external side effects; use
  isolated configured environments and explicit examples where appropriate.
- Files, streams and multipart inputs need their actual headers, content types,
  limits and access requirements. Do not present them as JSON.
- Use supported scenario/example annotations for the installed generator.
  Verify generated output and semantic HTTP/file/stream behavior.

## Status and Scenario Inventory

| Runtime branch | Documentation requirement |
|---|---|
| Normal JSON success | Actual status, envelope, complete example and fields |
| Created model | `201` only if actually returned |
| No content | `204` without invented JSON |
| Authentication | Actual guard/handler body; no assumed success-shaped error |
| Missing permission | Stable denial body when the route can return it |
| Binding/domain miss | Separate scenarios when their bodies differ |
| Validation | Exact real field names and locale; domain error stays separate |
| File/stream | Content type, access, disposition and output format |

Use scenario names that explain the cause. An HTTP status alone is not a
scenario. Do not document an unmatched route constraint as a Controller branch.

## Concrete Examples

For an endpoint returning the following real contract:

```php
/**
 * @response 201 scenario="Created" {"data":{"id":7,"title":"Example"}}
 * @response 422 scenario="Missing title" {
 *   "message": "Укажите заголовок.",
 *   "errors": {"title":["Укажите заголовок."]}
 * }
 */
```

The localized error above is illustrative, not Laravel's required default text.
Copy a response from the actual handler or a focused HTTP test. Document an
application `code` only if emitted; if an exception is converted to a body
without a code, do not invent one to complete a registry.

If the project maintains an error-code summary block, preserve its entries and
link each emitted code to a scenario. A particular heading, language or blank-line
format is a chosen documentation convention, not a Scribe parser requirement.

For complete response examples stored in files, verify paths, scenario names
and content. Preserve explicit examples even when response-field annotations
also carry individual examples; one field's value does not describe the body.
