@php
    $hourGroups = [];
    foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day) {
        $hours = ($siteSettings?->opening_hours ?? [])[$day] ?? null;
        if (!$hours) continue;
        $text = !empty($hours['closed']) ? 'Closed' : \Carbon\Carbon::createFromFormat('H:i',$hours['open'])->format('g:i a').' – '.\Carbon\Carbon::createFromFormat('H:i',$hours['close'])->format('g:i a');
        $last = count($hourGroups)-1;
        if ($last >= 0 && $hourGroups[$last]['text'] === $text) {
            $hourGroups[$last]['end'] = $day;
        } else {
            $hourGroups[] = ['start'=>$day,'end'=>$day,'text'=>$text];
        }
    }
@endphp
<div class="mmc-opening-hours">
@foreach($hourGroups as $group)
<p class="mmc-hours-row"><strong>{{ $group['start'] === $group['end'] ? $group['start'] : $group['start'].' – '.$group['end'] }}</strong><span>{{ $group['text'] }}</span></p>
@endforeach
</div>
