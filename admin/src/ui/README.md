# Phay UI kit

Shared building blocks of the Phay admin: buttons, inputs, cards, badges,
tables (`@/ui`) and dialogs (`@/ui/dialog/Dialog`, `@/ui/dialog/ConfirmDialog`).

Screens import from `@/ui` instead of relative paths, so the kit can later
move into its own package without touching them. Keep it free of API calls
and screen-specific logic.
