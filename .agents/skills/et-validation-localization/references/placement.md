# Placement And Rule Shape

Use this reference before changing DTO validation, custom rules, or
`lang/{project_locale}/validation.php`.

## Placement

- Put shared Laravel rule messages and repeated field names in
  `lang/{project_locale}/validation.php`.
- Discover the configured language path, locale and existing organization;
  `lang/{project_locale}/validation.php` is a conventional example, not a
  required layout.
- Put one-off aliases in the existing Form Request/DTO validator where supported.
- Add validator-local `messages()` only when the global project target language rule
  message plus field alias is not clear enough.
- Do not move rare single-use text into `validation.php` just to centralize it.

## Validator Rules

- With an attribute-based DTO package, prefer supported attributes for simple
  rules when that is the local pattern. Otherwise use the existing validator.
- Use the validator’s supported rules API for wildcards, conditional/contextual
  rules, scoped existence checks and cases attributes cannot express clearly.
- For simple arrays, numeric bounds, and file rules, check existing project
  attributes before adding manual `rules()`.

## Scope And Input Safety

- Conditional custom rules that must run on missing/null input are implicit or
  are not hidden behind `nullable`.
- Put validation-shaped context constraints in the established validator or
  custom Rule. Enforce authorization independently in policies/scoped access;
  do not require a DTO or move business invariants solely for consistency.
- External/import payload values are not pre-cast or normalized before
  validation when that would hide invalid source input.
- Shape validation and domain/business errors stay separate.
