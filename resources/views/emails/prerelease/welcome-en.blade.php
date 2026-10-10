@extends('layouts.emails')

@section('content')
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;max-width:600px;">
        @php
            $primaryColor = '#0078D4';
            $primaryContrast = '#A8D800';
            $fontColor = '#666';
            $fontHeader = '#FFFFFF';
        @endphp
        <tr>
            <td style="text-align: left;">
                <h1 style="font-size: 28px; color: {{ $fontHeader }}; text-align: left;">Hello {{ $signup->name }},</h1>
            </td>
        </tr>
        <tr>
            <td style="text-align: left;">
                <p style="font-size: 18px; color: {{ $fontColor }}; text-align: left;">
                    Thank you for joining the INAMAP community! We are thrilled to have you on board as we approach the launch of INAMAP, the interactive mapping platform that guides people through venues.
                </p>
            </td>
        </tr>
        <tr>
            <td style="text-align: left;">
                <p style="font-size: 18px; color: {{ $fontColor }}; text-align: left; font-weight: bold;">
                    What can you expect from INAMAP?
                </p>
                <ul style="font-size: 16px; color: {{ $fontColor }}; text-align: left; padding-left: 20px;">
                    <li style="margin-bottom: 8px;">A visual editor to draw your venue: floors, zones, buildings and points of interest.</li>
                    <li style="margin-bottom: 8px;">Step-by-step routes for your visitors, including accessible and multi-floor routes.</li>
                    <li style="margin-bottom: 8px;">Place search and QR codes to take the route to their phone.</li>
                </ul>
            </td>
        </tr>
        @if(!empty($brochureUrl))
        <tr>
            <td style="text-align: left;">
                <p style="font-size: 16px; color: {{ $fontColor }}; text-align: left;">
                    While we work on the final details, we invite you to explore our brochure to learn more about INAMAP and everything it will offer. You can download it by clicking the following link:
                </p>
                <div style="text-align: center; margin: 32px 0;">
                    <a href="{{ $brochureUrl }}"
                       style="display: inline-block; background: linear-gradient(135deg, {{ $primaryColor }} 0%, {{ $primaryContrast }} 100%); color: #fff; text-decoration: none; padding: 16px 40px; border-radius: 32px; font-size: 18px; font-weight: bold; margin: 0 auto; box-shadow: 0 4px 15px rgba(0,0,0,0.10); text-align: center;">
                        Download Brochure
                    </a>
                </div>
            </td>
        </tr>
        @endif
        <tr>
            <td style="text-align: left;">
                <p style="font-size: 16px; color: {{ $fontColor }}; text-align: left;">
                    Thank you again for your interest! We will let you know as soon as we are ready for the official launch.
                </p>
                <p style="font-size: 16px; color: {{ $fontColor }}; text-align: left;">
                    Best regards,<br>
                    The INAMAP team
                </p>
            </td>
        </tr>
    </table>
@endsection
