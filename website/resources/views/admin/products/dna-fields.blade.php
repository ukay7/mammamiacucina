@foreach(config('product_dna.'.$group) as $dnaKey=>$definition)
@php($value=old('dna.'.$dnaKey,data_get($product->dna,$dnaKey)))
<div class="form-group {{ $definition[1]==='textarea'?'col-12':'col-md-6 col-xl-4' }}">
<label for="dna-{{ $dnaKey }}">{{ $definition[0] }}</label>
@if($definition[1]==='select')
<select class="form-control" name="dna[{{ $dnaKey }}]" id="dna-{{ $dnaKey }}"><option value="">Not specified</option>@foreach($definition[2] as $option=>$label)<option value="{{ $option }}" @selected($value===$option)>{{ $label }}</option>@endforeach</select>
@elseif($definition[1]==='checkbox')
<input type="hidden" name="dna[{{ $dnaKey }}]" value="0"><input type="checkbox" id="dna-{{ $dnaKey }}" name="dna[{{ $dnaKey }}]" value="1" @checked($value)>
@elseif($definition[1]==='textarea')
<textarea class="form-control" rows="3" name="dna[{{ $dnaKey }}]" id="dna-{{ $dnaKey }}">{{ $value }}</textarea>
@else
<input class="form-control" type="{{ in_array($definition[1],['number','integer'])?'number':$definition[1] }}" step="{{ $definition[1]==='integer'?'1':'any' }}" min="{{ $definition[1]==='integer'?'1':'0' }}" name="dna[{{ $dnaKey }}]" id="dna-{{ $dnaKey }}" value="{{ $value }}">
@endif
</div>
@endforeach
