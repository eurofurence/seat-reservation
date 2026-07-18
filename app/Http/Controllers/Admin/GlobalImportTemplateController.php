<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class GlobalImportTemplateController extends Controller
{
    public function __invoke()
    {
        $csv = "Event,Guest Name,Comment,Block,Row,Seat,Number of Seats,Timestamp\n";
        $csv .= "Summer Gala,John Doe,VIP guest,A,1,1,,2025-06-15 10:00:00\n";
        $csv .= "Summer Gala,Jane Smith,,A,1,,2,2025-06-15 09:30:00\n";
        $csv .= "Winter Ball,Alex Lee,,,,,,\n";

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="booking-import-template-all-events.csv"');
    }
}
