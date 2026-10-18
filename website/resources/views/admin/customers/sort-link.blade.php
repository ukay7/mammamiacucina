@php($selected=request('sort','id')===$column)
<a href="{{ request()->fullUrlWithQuery(['sort'=>$column,'direction'=>$selected && request('direction','desc')==='asc'?'desc':'asc','page'=>1]) }}">{{ $label }}@if($selected) <span aria-hidden="true">{{ request('direction','desc')==='asc'?'↑':'↓' }}</span>@endif</a>
