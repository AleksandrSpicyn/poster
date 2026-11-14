# Concrete Review Examples

## Evidence Before Conclusions

```bash
gh pr view <number> --json baseRefName,headRefName,headRefOid,files,statusCheckRollup
gh pr diff <number> --patch
git diff --check
```

Use the actual repository and PR. Read the submitted patch before fixers. Check
route, validator, use case, query, Resource, test and consumers for changed paths.
A green CI result does not establish owner scope or serializer correctness.

## Before / After a Useful Test Finding

Weak candidate: "add more tests".

Actionable finding: "The list test asserts only one item. It passes if that item
belongs to another actor. Add a foreign-owner record and assert the exact returned
IDs so the test detects loss of the ownership filter."

## Layering Finding Under the Adopted Profile

When the repository adopted `Controller → Action → Task → Repository`, a new
Controller calling Repository directly violates that rule. State the concrete
bypass and move orchestration into the scenario's Action/Task; do not just paste
an architecture diagram. In a repository that intentionally uses Controller →
Service/Eloquent, the mere absence of Tasks is not a defect.

## PR Comment Shape

```text
The new Resource emits author.email for the public list. The query authorizes
article access but does not authorize exposure of the author's private email.
Use the public author projection and add an assertion that email is absent.
```

One issue per comment: trigger, impact, expected correction. Cite exact changed
lines. Public fields, guards and route names in real comments come from current
code, not from this fictional example. Post only when the user requested posting;
otherwise return the findings as drafts.
