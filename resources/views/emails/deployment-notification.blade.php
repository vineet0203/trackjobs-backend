<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $data['success'] ? '✅ Deployment Successful' : '❌ Deployment Failed' }}</title>
    <!--[if mso]>
    <xml>
        <o:OfficeDocumentSettings>
            <o:AllowPNG/>
            <o:PixelsPerInch>96</o:PixelsPerInch>
        </o:OfficeDocumentSettings>
    </xml>
    <style>
        table {border-collapse: collapse;}
        .spacer {display: none !important;}
    </style>
    <![endif]-->
    <style>
        /* Mobile-first responsive styles */
        @media only screen and (max-width: 640px) {
            .container {
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 10px !important;
            }
            
            .content-cell {
                padding: 20px 15px !important;
            }
            
            .header-cell {
                padding: 30px 15px 10px 15px !important;
            }
            
            h1 {
                font-size: 28px !important;
                line-height: 1.2 !important;
            }
            
            h2 {
                font-size: 18px !important;
                line-height: 1.3 !important;
            }
            
            .stats-table td {
                display: block !important;
                width: 100% !important;
                padding: 8px 0 !important;
            }
            
            .commit-box {
                margin: 20px 0 !important;
                padding: 15px !important;
            }
            
            .commit-table td {
                display: block !important;
                width: 100% !important;
                padding: 4px 0 !important;
            }
            
            .cta-button {
                display: block !important;
                text-align: center !important;
                padding: 12px 20px !important;
                font-size: 14px !important;
            }
            
            .footer-cell {
                padding: 20px 15px !important;
            }
            
            .footer-links a {
                display: block !important;
                margin: 5px 0 !important;
                padding: 0 !important;
            }
            
            .footer-links span {
                display: none !important;
            }
        }
        
        /* Desktop styles */
        @media only screen and (min-width: 769px) {
            .container {
                width: 100% !important;
                max-width: 600px !important;
            }
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            body {
                background-color: #121212 !important;
            }
            
            .email-container {
                background-color: #1e1e1e !important;
                color: #e0e0e0 !important;
            }
            
            h1, h2, h3, strong {
                color: #ffffff !important;
            }
            
            p, td {
                color: #b0b0b0 !important;
            }
            
            .commit-box, .error-box {
                background-color: #2d2d2d !important;
                border-color: #404040 !important;
            }
            
            hr {
                border-color: #404040 !important;
            }
            
            .footer-cell {
                background-color: #2d2d2d !important;
                color: #b0b0b0 !important;
            }
            
            .footer-links a {
                color: #b0b0b0 !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; color: #333333; background-color: #f5f5f5; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
    <!--[if mso]>
    <div style="background-color: #f5f5f5;">
    <![endif]-->
    <center>
        <table width="100%" cellpadding="0" cellspacing="0" border="0" class="container" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; width: 100%;">
            <!-- Header -->
            <tr>
                <td class="header-cell" style="padding: 40px 35px 15px 35px; color: #161616; background-color: {{ $data['success'] ? '#10b981' : '#ef4444' }}; color: white;">
                    <h1 style="margin: 0 0 10px 0; font-size: 32px; font-weight: 700; letter-spacing: 0.5px; text-align: center; color: white;">
                        {{ $data['success'] ? '🚀 Deployment Successful!' : '⚠️ Deployment Failed!' }}
                    </h1>
                    <p class="title-text" style="margin: 0; font-size: 16px; font-weight: 400; opacity: 0.9; text-align: center; color: white;">
                        {{ $data['success'] ? 'Your changes are now live in ' . strtoupper($data['environment'] ?? 'production') : 'Action required - deployment did not complete' }}
                    </p>
                </td>
            </tr>
            
            <!-- Divider -->
            <tr>
                <td style="padding: 0 35px;">
                    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 0;">
                </td>
            </tr>

            <!-- Main Content -->
            <tr>
                <td class="content-cell" style="padding: 0 35px 30px 35px;">
                    <!-- Status Badges -->
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 25px 0 20px 0;">
                        <tr>
                            <td align="center">
                                <span style="display: inline-block; background-color: {{ $data['success'] ? '#d1fae5' : '#fee2e2' }}; color: {{ $data['success'] ? '#065f46' : '#991b1b' }}; padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; margin: 0 5px 5px 0;">
                                    {{ $data['success'] ? 'SUCCESS' : 'FAILED' }}
                                </span>
                                <span style="display: inline-block; background-color: #dbeafe; color: #1e40af; padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; margin: 0 5px 5px 0;">
                                    {{ strtoupper($data['environment'] ?? 'PROD') }}
                                </span>
                                <span style="display: inline-block; background-color: #f3e8ff; color: #6b21a8; padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; margin: 0 0 5px 0;">
                                    {{ number_format($data['duration'], 2) }}s
                                </span>
                            </td>
                        </tr>
                    </table>

                    <!-- Quick Stats -->
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" class="stats-table" style="margin: 0 0 25px 0; background-color: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px;">
                        <tr>
                            <td colspan="3" style="padding: 0 0 15px 0;">
                                <h2 style="margin: 0; font-size: 17px; color: #111827; font-weight: 600; text-align: center;">Deployment Summary</h2>
                            </td>
                        </tr>
                        <tr>
                            <td align="center" style="padding: 0 15px; width: 33%;">
                                <div style="font-size: 24px; font-weight: 700; color: {{ $data['success'] ? '#10b981' : '#ef4444' }}; margin-bottom: 5px;">
                                    @if($data['success']) ✅ @else ❌ @endif
                                </div>
                                <div style="font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Status</div>
                            </td>
                            <td align="center" style="padding: 0 15px; width: 33%;">
                                <div style="font-size: 24px; font-weight: 700; color: #3b82f6; margin-bottom: 5px;">{{ number_format($data['duration'], 1) }}s</div>
                                <div style="font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Duration</div>
                            </td>
                            <td align="center" style="padding: 0 15px; width: 33%;">
                                <div style="font-size: 24px; font-weight: 700; color: #8b5cf6; margin-bottom: 5px;">
                                    <span style="display: inline-block; width: 40px; height: 40px; line-height: 40px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: white; font-size: 16px; font-weight: 700;">
                                        {{ substr($data['commit']['author'] ?? '?', 0, 1) }}
                                    </span>
                                </div>
                                <div style="font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Author</div>
                            </td>
                        </tr>
                    </table>

                    <!-- Commit Details -->
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" class="commit-box" style="background-color: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; margin: 0 0 25px 0; padding: 20px;">
                        <tr>
                            <td>
                                <h2 style="margin: 0 0 15px 0; font-size: 17px; color: #111827; font-weight: 600; border-bottom: 2px solid #3b82f6; padding-bottom: 8px;">Commit Details</h2>
                                <table width="100%" cellpadding="0" cellspacing="0" border="0" class="commit-table" style="font-size: 14px; color: #4b5563;">
                                    <tr>
                                        <td style="padding: 8px 0; width: 100px;"><strong>Author:</strong></td>
                                        <td style="padding: 8px 0;">{{ $data['commit']['author'] ?? 'Unknown' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0;"><strong>Commit:</strong></td>
                                        <td style="padding: 8px 0; font-family: 'SFMono-Regular', Consolas, monospace;">
                                            {{ substr($data['commit']['id'] ?? 'unknown', 0, 8) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; vertical-align: top;"><strong>Message:</strong></td>
                                        <td style="padding: 8px 0; line-height: 1.6;">{{ $data['commit']['message'] ?? 'No message' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0;"><strong>Timestamp:</strong></td>
                                        <td style="padding: 8px 0;">{{ date('M d, Y \a\t h:i A', strtotime($data['timestamp'] ?? now())) }}</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                    <!-- Error Section -->
                    @if(!$data['success'] && !empty($data['error']))
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" class="error-box" style="background-color: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #ef4444; border-radius: 8px; margin: 0 0 25px 0; padding: 20px;">
                        <tr>
                            <td>
                                <h2 style="margin: 0 0 15px 0; font-size: 17px; color: #991b1b; font-weight: 600;">⚠️ Error Details</h2>
                                <p style="margin: 0; font-size: 14px; color: #7f1d1d; line-height: 1.5; font-family: 'SFMono-Regular', Consolas, monospace; background-color: white; padding: 12px; border-radius: 6px;">
                                    {{ $data['error'] }}
                                </p>
                            </td>
                        </tr>
                    </table>
                    @endif

                    <!-- Backup Section -->
                    @if($data['backup'])
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f0f9ff; border: 1px solid #7dd3fc; border-left: 4px solid #0ea5e9; border-radius: 8px; margin: 0 0 25px 0; padding: 20px;">
                        <tr>
                            <td>
                                <h2 style="margin: 0 0 10px 0; font-size: 17px; color: #0369a1; font-weight: 600;">💾 Backup Created</h2>
                                <p style="margin: 0; font-size: 14px; color: #0c4a6e; line-height: 1.5;">
                                    A backup was successfully created at:<br>
                                    <code style="background: #e0f2fe; padding: 4px 8px; border-radius: 4px; font-family: 'SFMono-Regular', Consolas, monospace; font-size: 13px;">
                                        {{ basename($data['backup']) }}
                                    </code>
                                </p>
                            </td>
                        </tr>
                    </table>
                    @endif

                    <!-- Action Buttons -->
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 30px 0 25px 0;">
                        <tr>
                            <td align="center">
                                <a href="https://github.com/rajpootsourabh/trackjobs-backend/commit/{{ $data['commit']['id'] ?? '' }}" 
                                   class="cta-button" 
                                   style="display: inline-block; background-color: #3b82f6; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 15px; padding: 12px 24px; border-radius: 6px; text-align: center; margin: 0 5px 10px 0;">
                                    📝 View Commit
                                </a>
                                <a href="https://github.com/rajpootsourabh/trackjobs-backend" 
                                   class="cta-button" 
                                   style="display: inline-block; background-color: #64748b; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 15px; padding: 12px 24px; border-radius: 6px; text-align: center; margin: 0 0 10px 5px;">
                                    🔍 View Repository
                                </a>
                            </td>
                        </tr>
                    </table>

                    <!-- Repository Info -->
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 20px 0; font-size: 14px; color: #4b5563;">
                        <tr>
                            <td style="padding: 5px 0;"><strong>Repository:</strong></td>
                            <td style="padding: 5px 0;">rajpootsourabh/trackjobs-backend</td>
                        </tr>
                        <tr>
                            <td style="padding: 5px 0;"><strong>Branch:</strong></td>
                            <td style="padding: 5px 0;">main</td>
                        </tr>
                        <tr>
                            <td style="padding: 5px 0; vertical-align: top;"><strong>Environment:</strong></td>
                            <td style="padding: 5px 0;">
                                <span style="display: inline-block; background-color: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                    {{ strtoupper($data['environment'] ?? 'PRODUCTION') }}
                                </span>
                            </td>
                        </tr>
                    </table>

                    <!-- Closing -->
                    <p style="margin: 0 0 10px 0; font-size: 15px; color: #4b5563; line-height: 1.6;">
                        This is an automated deployment notification from your {{ config('app.name') }} deployment system.
                    </p>
                    
                    @if(!$data['success'])
                    <p style="margin: 0 0 15px 0; font-size: 15px; color: #991b1b; font-weight: 600; line-height: 1.6;">
                        ⚠️ Immediate action required: Please review the error and fix the deployment issue.
                    </p>
                    @endif

                    <!-- Signature -->
                    <p style="margin: 0 0 5px 0; font-size: 15px; color: #4b5563;">Best regards,</p>
                    <p style="margin: 0; font-size: 15px; color: #111827; font-weight: 600;">{{ config('app.name') }} Deployment System</p>
                </td>
            </tr>

            <!-- Divider -->
            <tr>
                <td>
                    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 0;">
                </td>
            </tr>

            <!-- Footer -->
            <tr>
                <td class="footer-cell" align="center" style="padding: 25px 35px; font-size: 12px; color: #6b7280; background-color: #f9fafb;">
                    <p style="margin: 0 0 8px 0; line-height: 1.5;">
                        You're receiving this email because you're subscribed to deployment notifications for {{ config('app.name') }}.
                    </p>
                    <p style="margin: 0 0 15px 0; line-height: 1.5;">
                        This deployment was triggered by a push to the main branch.
                    </p>

                    <p style="margin: 0 0 12px 0;">© {{ date('Y') }} {{ config('app.name') }}. All Rights Reserved</p>

                    <p class="footer-links" style="margin: 0;">
                        <a href="{{ config('app.url') }}/settings/notifications" style="color: #6b7280; text-decoration: none; margin: 0 6px;">Notification Settings</a><span style="color: #6b7280;"> | </span>
                        <a href="{{ config('app.url') }}/deployment/history" style="color: #6b7280; text-decoration: none; margin: 0 6px;">Deployment History</a><span style="color: #6b7280;"> | </span>
                        <a href="mailto:support@traktjobs.com" style="color: #6b7280; text-decoration: none; margin: 0 6px;">Support</a>
                    </p>
                </td>
            </tr>
        </table>
    </center>
    <!--[if mso]>
    </div>
    <![endif]-->
</body>
</html>