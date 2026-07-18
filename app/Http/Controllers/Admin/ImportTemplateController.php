<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class ImportTemplateController extends Controller
{
    public function __invoke($id)
    {
        $csv = "Guest Name,Comment,Block,Row,Seat,Number of Seats,Timestamp\n";
        $csv .= "John Doe,VIP guest,A,1,1,,2025-06-15 10:00:00\n";
        $csv .= "Jane Smith,,A,1,,2,2025-06-15 09:30:00\n";
        $csv .= "Sam Rowan,,A,1,,2,2025-06-15 09:45:00\n";
        $csv .= "Alex Lee,,center,,,2,\n";
        $csv .= "Pat Blank,,,,,,\n";

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="booking-import-template.csv"');
    }
}
