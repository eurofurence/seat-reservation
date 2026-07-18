@extends('pdf.card-layout')

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
@endsection
