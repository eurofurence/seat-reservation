@extends('pdf.card-layout')

@section('styles')
    .card-name { font-size: 90pt; }
    .event-name {
        position: fixed;
        bottom: 20mm;
        left: 0;
        right: 0;
        margin: 0;
        width: 100%;
        text-align: center;
        font-size: 17pt;
        line-height: 1;
        color: #d2d2d2;
    }
    .bigger {
        font-size: 20pt;
    }
@endsection

@section('content')
    <div class="text-content">
        <div class="card-name">
            @if($booking->name)
                {{ Str::limit($booking->name, 24, '') }}
            @elseif($booking->user)
                {{ Str::limit($booking->user->name, 24, '') }}
            @else
                Unknown
            @endif
        </div>
        <div class="card-location">
            Block {{ $booking->seat->row->block->name }} {{ $booking->seat->row->name }} Seat {{ $booking->seat->label }}
        </div>
    </div>
@endsection

@section('overlay')
    @if(is_null($booking->picked_up_at))
    <div class="not-picked-up-wrapper">
        <table style="border-collapse: collapse; width: 1px;">
            <tr><td class="not-picked-up">Not Picked Up</td></tr>
        </table>
    </div>
    @endif
    <div class="event-name">
        for
        <br>
        <span class="bigger">{{ $event->name }}</span>
    </div>
@endsection
