<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* Orbitron font is handled by mPDF configuration */

        body {
            font-family: 'orbitron', 'DejaVu Sans', sans-serif;
            margin: 0;
            padding: 0;
            width: 297mm;
            height: 210mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;

        }

        .card-content {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
            background-image: url('file://{{ resource_path('/assets/images/seating_card.png')  }}');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center center;
        }

        .text-content {
            padding-top: 75mm;
            text-align: center;
        }

        .card-name {
            font-family: 'orbitron', 'DejaVu Sans', sans-serif;
            font-size: 40pt;
            font-weight: bold;
            color: #000;
            margin-bottom: 20mm;
            width: 60%;
            margin-left: auto;
            margin-right: auto;
            text-transform: uppercase;
            letter-spacing: 2px;
            word-wrap: break-word;
            line-height: 1.2;
        }

        .card-location {
            font-family: 'orbitron', 'DejaVu Sans', sans-serif;
            font-size: 24pt;
            font-weight: normal;
            color: #000;
            text-transform: uppercase;
            letter-spacing: 1px;
            line-height: 1.6;
            display: flex;
        }
    </style>
</head>
<body>
    <div class="card-content">
        <div class="text-content">
            <div class="card-name">
                @if($booking->name)
                    {{ $booking->name }}
                @elseif($booking->user)
                    {{ $booking->user->name }}
                @else
                    Unknown
                @endif
            </div>
            <div class="card-location">
                Block {{ $booking->seat->row->block->name }} {{ $booking->seat->row->name }} Seat {{ $booking->seat->label }}
            </div>
        </div>
    </div>
</body>
</html>
