@extends('pdf.card-layout')

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
