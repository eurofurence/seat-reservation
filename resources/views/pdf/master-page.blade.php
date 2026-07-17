@extends('pdf.card-layout')

@section('styles')
    .event-name { font-size: 52pt; margin-top: 0.2em; margin-bottom: 1mm; }
    .block-name { font-size: 26pt; margin-bottom: 3mm; }
@endsection

@section('content')
    <div class="text-content">
        <div class="event-name">
            @if($event_name)
                {{ $event_name }}
            @else
                Unknown
            @endif
        </div>
        <div class="block-name">
            Layout
        </div>
        @if(!empty($overview))
            <div class="block-preview master-overview">
                {!! $overview !!}
            </div>
        @endif
    </div>
@endsection

@section('overlay')
@endsection
