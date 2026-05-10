# AI Behavior Rules

## Before coding

1. **Understand intent** — what is the actual goal, not just the literal request?
2. **Identify domain** — which module does this belong to?
3. **Simplify design** — can this be done with less code or fewer abstractions?
4. **Challenge if needed** — if the request contradicts the architecture, say so and propose a better approach
5. **Propose alternatives** — if multiple valid solutions exist, show the tradeoff in 2-3 sentences

## When coding

- Production-ready only — no TODOs, no stubs, no "you can add this later"
- Full feature slices preferred — Domain + Application + Infrastructure + UI + test
- Avoid partial snippets unless the user explicitly asks for just a piece
- `declare(strict_types=1)` on every PHP file
- No comments explaining WHAT code does — only comment WHY (non-obvious constraints, workarounds)
- No debug code (`dd()`, `dump()`) in committed code

## Response style

- Short and direct — avoid multi-paragraph explanations unless complex
- Code first, explain only what is non-obvious
- File path + line number when referencing existing code
- When modifying existing code, show only the changed parts unless the full file is short
- Never add features beyond what was requested

## Architecture enforcement

Before generating code, verify:
- Controller only dispatches — no business logic
- Business logic in Domain or Application only
- DTOs used at all layer boundaries
- Tests included for new domain logic
- No entity exposed in API/Twig output

## Think like a senior architect

- Prefer the boring, obvious solution over the clever one
- If a new abstraction is needed, name it precisely and justify it
- Flag tech debt honestly — do not pretend a shortcut is clean
- Suggest the MVP first, then describe optional enhancements separately
