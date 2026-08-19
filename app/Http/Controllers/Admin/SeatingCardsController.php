<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Booking;
use App\Models\Event;
use App\Services\Svg\MasterCardSvgGenerator;
use App\Services\Svg\OrderCardSvgGenerator;
use App\Services\Svg\SvgUtilities;
use Illuminate\Support\Str;

class SeatingCardsController extends Controller
{
    public function __invoke($id)
    {
        $event = Event::with('room')->findOrFail($id);

        try {
            $bookingsQuery = Booking::where('event_id', $id)
                ->select('id', 'event_id', 'user_id', 'seat_id', 'name', 'picked_up_at')
                ->with([
                    'user:id,name',
                    'seat:id,row_id,label,number',
                    'seat.row:id,block_id,name,order',
                    'seat.row.block:id,name,position_x,position_y',
                ]);

            if (! request()->boolean('include_unpicked')) {
                $bookingsQuery->whereNotNull('picked_up_at');
            }

            // Wind seats by row order so placing the cards is easier and faster for the runners.
            $bookings = $bookingsQuery->get()->sortBy(function ($booking) {
                $block = $booking->seat->row->block;
                $rowOrder = $booking->seat->row->order;
                $seatNumber = $booking->seat->number;

                $seatSort = $rowOrder % 2 === 1 ? $seatNumber : -$seatNumber;

                return [
                    $block->position_y,
                    $block->position_x,
                    $rowOrder,
                    $seatSort,
                ];
            });

            if ($bookings->isEmpty()) {
                return back()->with('error', 'No bookings found for this event to generate seating cards.');
            }

            $blockIds = $bookings->pluck('seat.row.block.id')->unique()->all();

            $previewBlocks = Block::whereIn('id', $blockIds)
                ->with(['rows' => function ($query) {
                    $query->select('id', 'block_id', 'name', 'order', 'alignment')
                        ->orderBy('order')
                        ->with(['seats' => function ($q) {
                            $q->select('id', 'row_id', 'label', 'number')->orderBy('number');
                        }]);
                }])
                ->get()
                ->keyBy('id');

            $bookedSeatIds = array_fill_keys($bookings->pluck('seat.id')->all(), true);

            $room = $event->room;
            $masterBlocks = $room->blocks()
                ->select('id', 'room_id', 'name', 'position_x', 'position_y', 'rotation', 'colspan', 'rowspan')
                ->with(['rows' => function ($query) {
                    $query->select('id', 'block_id', 'order', 'alignment')
                        ->orderBy('order')
                        ->with(['seats:id,row_id,number']);
                }])
                ->get();
            $masterStageBlocks = $room->stageBlocks()
                ->select('id', 'room_id', 'name', 'position_x', 'position_y', 'colspan', 'rowspan')
                ->get();
            $masterMarkerBlocks = $room->markerBlocks()
                ->select('id', 'room_id', 'name', 'type', 'position_x', 'position_y', 'rotation', 'colspan', 'rowspan')
                ->get();

            // mPDF configuration with custom Zhurzh font
            $mpdf = new \Mpdf\Mpdf([
                'format' => 'A4-L',
                'margin_left' => 0,
                'margin_right' => 0,
                'margin_top' => 0,
                'margin_bottom' => 0,
                'margin_header' => 0,
                'margin_footer' => 0,
                'fontDir' => [resource_path('assets/fonts')],
                'fontdata' => [
                    'zhurzh' => [
                        'R' => 'Zhurzh.ttf',
                    ],
                ],
                'default_font' => 'zhurzh',
            ]);

            // Set execution time limit for large batches
            set_time_limit(300); // 5 minutes

            $svg = new SvgUtilities;
            $masterCard = new MasterCardSvgGenerator($svg);
            $orderCard = new OrderCardSvgGenerator($svg);

            $pages = [];

            $masterCardMaxheight = self::masterCardMaxHeight($event->name);
            $orderCardMaxheight = self::orderCardMaxHeight($event->name);

            $totalCards = 1;

            $currentBlockId = null;
            foreach ($bookings as $booking) {
                $block = $booking->seat->row->block;

                if ($block->id !== $currentBlockId) {
                    $currentBlockId = $block->id;

                    $totalCards++;
                }

                $totalCards++;
            }

            $currentCard = 1;

            $pages[] = view('pdf.master-page', [
                'pagination' => ($currentCard++).'/'.$totalCards,
                'event_name' => $event->name,
                'room_name' => $room->name,
                'total_bookings' => $bookings->count(),
                'overview' => $masterCard->render($masterBlocks, $masterStageBlocks, $masterMarkerBlocks, $bookedSeatIds, $mpdf, $masterCardMaxheight),
            ])->render();

            $currentBlockId = null;

            foreach ($bookings as $booking) {
                $block = $booking->seat->row->block;

                if ($block->id !== $currentBlockId) {
                    $currentBlockId = $block->id;

                    $previewBlock = $previewBlocks->get($block->id);

                    $pages[] = view('pdf.order-card', [
                        'pagination' => ($currentCard++).'/'.$totalCards,
                        'info' => (object) [
                            'event_name' => $event->name,
                            'block_name' => 'Block '.$block->name,
                        ],
                        'preview' => $previewBlock
                            ? $orderCard->render($previewBlock, $bookedSeatIds, $mpdf, $orderCardMaxheight)
                            : null,
                    ])->render();
                }

                $pages[] = view('pdf.seating-card-single', [
                    'pagination' => ($currentCard++).'/'.$totalCards,
                    'booking' => $booking,
                    'event' => $event,
                ])->render();
            }

            // Write each pre-rendered page to the PDF.
            foreach ($pages as $index => $html) {
                if ($index > 0) {
                    $mpdf->AddPage();
                }

                $mpdf->WriteHTML($html);
            }

            // Return PDF for browser preview
            $filename = 'seating-cards-'.Str::slug($event->name).'-'.date('Y-m-d').'.pdf';

            return response($mpdf->Output($filename, 'S'))
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="'.$filename.'"');

        } catch (\Exception $e) {
            \Log::error('Seating cards generation failed: '.$e->getMessage(), ['event_id' => $event->id, 'exception' => $e]);

            return back()->with('error', 'Failed to generate seating cards. Please try again or contact support.');
        }
    }

    // ponytail: 19-char cutoff is a rough heuristic, not measured PDF layout. Upgrade
    // path: measure the actual rendered text width and size the master card from that.
    public static function masterCardMaxHeight(string $eventName): int
    {
        return Str::length($eventName) > 19 ? 90 : 110;
    }

    // ponytail: 19-char cutoff is a rough heuristic, not measured PDF layout. Upgrade
    // path: measure the actual rendered text width and size the order card from that.
    public static function orderCardMaxHeight(string $eventName): int
    {
        return Str::length($eventName) > 19 ? 90 : 105;
    }
}
