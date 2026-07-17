@extends('pdf.card-layout')

@section('styles')
    .text-content { padding-top: 24mm; }
    .event-name { margin-bottom: 2mm; margin-top: 22mm; }
    .block-name { font-size: 52pt; margin-bottom: 2mm; }
@endsection

@section('content')
    <div class="text-content">
        <div class="event-name">
            @if($info->event_name)
                {{ $info->event_name }}
            @else
                Unknown
            @endif
        </div>
        <div class="block-name">
            @if($info->block_name)
                {{ $info->block_name }}
            @else
                Unknown
            @endif
        </div>
        @if(!empty($preview))
            <div class="block-preview">
                {!! $preview !!}
            </div>
        @endif
    </div>
@endsection

@section('overlay')
@endsection
