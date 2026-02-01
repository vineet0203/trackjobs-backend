{{-- resources/views/emails/client-welcome.blade.php --}}
@component('mail::message')
# Welcome to {{ $vendor->business_name }}!

Hello {{ $client->first_name }},

Your client account has been created for **{{ $vendor->business_name }}**.

## Account Details:
- **Email:** {{ $client->email }}
- **Vendor:** {{ $vendor->business_name }}

@if($temporaryPassword)
### Temporary Password:
{{ $temporaryPassword }}

**Please change your password after first login.**
@endif

## What you can do:
- Request new services
- Track ongoing jobs
- View and pay invoices
- Communicate with service providers

@component('mail::button', ['url' => $loginUrl])
Access Client Portal
@endcomponent

If you have any questions, please contact {{ $vendor->business_name }} support.

Thanks,<br>
{{ $vendor->business_name }} Team
@endcomponent