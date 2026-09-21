Hi {{ $inviterName }} has invited you to share the budget "{{ $budgetName }}" as {{ $role }}.

@if($isNewUser)
You don't have an account yet. Create one with this exact email address, then accept the invitation:
@else
You already have an account. Sign in and accept the invitation:
@endif

{{ $acceptUrl }}

@if($expiresAt)
This invitation expires on {{ $expiresAt }}.
@endif

Roles:
- editor: view + add/edit expenses and allocations (cannot manage partners or delete the budget)
- viewer: view-only access to dashboard, reports and expenses

If you didn't expect this email, you can ignore it.
