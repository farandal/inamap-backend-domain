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
                <h1 style="font-size: 28px; color: {{ $fontHeader }}; text-align: left;">Hola {{ $signup->name }},</h1>
            </td>
        </tr>
        <tr>
            <td style="text-align: left;">
                <p style="font-size: 18px; color: {{ $fontColor }}; text-align: left;">
                    ¡Gracias por unirte a la comunidad de INAMAP! Estamos encantados de tenerte a bordo mientras nos acercamos al lanzamiento de INAMAP, la plataforma de mapas interactivos para orientar a las personas dentro de recintos.
                </p>
            </td>
        </tr>
        <tr>
            <td style="text-align: left;">
                <p style="font-size: 18px; color: {{ $fontColor }}; text-align: left; font-weight: bold;">
                    ¿Qué puedes esperar de INAMAP?
                </p>
                <ul style="font-size: 16px; color: {{ $fontColor }}; text-align: left; padding-left: 20px;">
                    <li style="margin-bottom: 8px;">Un editor visual para dibujar los planos de tu recinto: pisos, zonas, edificios y puntos de interés.</li>
                    <li style="margin-bottom: 8px;">Rutas paso a paso para tus visitantes, también accesibles y entre pisos.</li>
                    <li style="margin-bottom: 8px;">Búsqueda de lugares y códigos QR para llevar la ruta al celular.</li>
                </ul>
            </td>
        </tr>
        @if(!empty($brochureUrl))
        <tr>
            <td style="text-align: left;">
                <p style="font-size: 16px; color: {{ $fontColor }}; text-align: left;">
                    Mientras trabajamos en los últimos detalles, te invitamos a explorar nuestro brochure para conocer más sobre INAMAP y todo lo que ofrecerá. Puedes descargarlo haciendo clic en el siguiente enlace:
                </p>
                <div style="text-align: center; margin: 32px 0;">
                    <a href="{{ $brochureUrl }}"
                       style="display: inline-block; background: linear-gradient(135deg, {{ $primaryColor }} 0%, {{ $primaryContrast }} 100%); color: #fff; text-decoration: none; padding: 16px 40px; border-radius: 32px; font-size: 18px; font-weight: bold; margin: 0 auto; box-shadow: 0 4px 15px rgba(0,0,0,0.10); text-align: center;">
                        Descargar Brochure
                    </a>
                </div>
            </td>
        </tr>
        @endif
        <tr>
            <td style="text-align: left;">
                <p style="font-size: 16px; color: {{ $fontColor }}; text-align: left;">
                    ¡Gracias de nuevo por tu interés! Te avisaremos tan pronto como estemos listos para el lanzamiento oficial.
                </p>
                <p style="font-size: 16px; color: {{ $fontColor }}; text-align: left;">
                    Un cordial saludo,<br>
                    El equipo de INAMAP
                </p>
            </td>
        </tr>
    </table>
@endsection
