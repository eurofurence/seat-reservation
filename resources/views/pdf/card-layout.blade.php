<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'zhurzh', 'DejaVu Sans', sans-serif;
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
            padding-top: 43mm;
            text-align: center;
        }

        .card-name {
            font-family: 'zhurzh', 'DejaVu Sans', sans-serif;
            font-size: 80pt;
            font-weight: bold;
            color: #000;
            margin-top: 15mm;
            margin-bottom: 5mm;
            width: 75%;
            margin-left: auto;
            margin-right: auto;
            text-transform: uppercase;
            letter-spacing: 2px;
            word-wrap: break-word;
            line-height: 1.2;
        }

        /* Order-card divider headings */
        .event-name {
            font-family: 'zhurzh', 'DejaVu Sans', sans-serif;
            font-size: 40pt;
            font-weight: bold;
            color: #000;
            margin-top: 15mm;
            margin-bottom: 4mm;
            width: 80%;
            margin-left: auto;
            margin-right: auto;
            text-transform: uppercase;
            letter-spacing: 2px;
            word-wrap: break-word;
            line-height: 1.1;
        }

        .block-name {
            font-family: 'zhurzh', 'DejaVu Sans', sans-serif;
            font-size: 60pt;
            font-weight: bold;
            color: #000;
            margin-bottom: 5mm;
            width: 80%;
            margin-left: auto;
            margin-right: auto;
            text-transform: uppercase;
            letter-spacing: 2px;
            word-wrap: break-word;
            line-height: 1.1;
        }

        .card-location {
            font-family: 'zhurzh', 'DejaVu Sans', sans-serif;
            font-size: 32pt;
            font-weight: normal;
            color: #000;
            text-transform: uppercase;
            letter-spacing: 1px;
            line-height: 1.6;
            display: flex;
        }

        .block-preview {
            width: 72%;
            margin: 0 auto;
            text-align: center;
        }

        .master-overview {
            width: 100%;
            text-align: center;
        }

        .master-overview svg {
            display: inline-block;
        }

        .not-picked-up {
            font-family: 'zhurzh', 'DejaVu Sans', sans-serif;
            font-size: 28pt;
            font-weight: bold;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 2px;
            background-color: #000;
            padding: 4mm 10mm;
            padding-bottom: 3mm;
            white-space: nowrap;
        }

        .not-picked-up-wrapper {
            position: fixed;
            top: 20mm;
            right: 20mm;
        }

        .pagination {
            position: fixed;
            top: 20mm;
            left: 20mm;
            width: 100mm;
            margin: 0;
            font-size: 17pt;
            line-height: 1;
            text-align: left;
            white-space: nowrap;

            color: #d2d2d2;
        }

        @yield('styles')
    </style>
</head>
<body>
    <div class="card-content">
        @yield('content')
    </div>
    @yield('overlay')

    @if(!empty($pagination))
    <div class="pagination">
        {{ $pagination }}
    </div>
    @endif
</body>
</html>
