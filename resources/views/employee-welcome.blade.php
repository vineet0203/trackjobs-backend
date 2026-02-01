{{-- resources/views/emails/employee-welcome.blade.php --}}
@component('mail::message')
# Welcome to {{ $vendor->business_name }}!

Hello {{ $employee->first_name }},

Your employee account has been created for **{{ $vendor->business_name }}**.

## Account Details:
- **Email:** {{ $employee->email }}
- **Role:** Employee
- **Vendor:** {{ $vendor->business_name }}

@if($temporaryPassword)
### Temporary Password:
{{ $temporaryPassword }}

**Please change your password after first login.**
@endif

## Next Steps:
1. Log in to the employee portal
2. Complete your profile
3. View your assigned jobs and schedule

@component('mail::button', ['url' => $loginUrl])
Login to Employee Portal
@endcomponent

If you have any questions, please contact your manager or {{ $vendor->business_name }} administration.

Thanks!
@endcomponent