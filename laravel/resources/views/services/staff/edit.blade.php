@extends('services.staff.layout')
@section('title', 'Edit service page')
@section('staff-title', 'Edit service page')
@section('staff-content')
@if($visualManaged)
<div class="notice">This page is managed with the visual editor. Its existing structured fields are retained as the original source.</div>
<a class="button" href="{{ route('services.staff.visual', 'page-'.$page->id) }}">Open frontend visual editor ↗</a>
@else
<p><a href="{{ route('services.staff.content') }}">← Back to content</a></p><form class="form-panel" method="post" action="{{ route('services.staff.content.update', $page) }}">@csrf @method('PATCH')
<div class="form-grid">@foreach(['title'=>'Page title','eyebrow'=>'Short heading label','frequency'=>'Frequency options'] as $field=>$label)<div class="field"><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" value="{{ old($field,$page->{$field}) }}" maxlength="{{ ['title'=>160,'eyebrow'=>120,'frequency'=>300][$field] }}" required></div>@endforeach<div class="field"><label for="published">Visibility</label><select id="published" name="published"><option value="1" @selected((string) old('published',(int)$page->published)==='1')>Published</option><option value="0" @selected((string) old('published',(int)$page->published)==='0')>Unpublished</option></select></div></div>
@foreach(['summary'=>['Short introduction',500], 'description'=>['Main description',6000], 'preparation'=>['Customer preparation',3000]] as $field=>$meta)<div class="field"><label for="{{ $field }}">{{ $meta[0] }}</label><textarea id="{{ $field }}" name="{{ $field }}" maxlength="{{ $meta[1] }}" rows="{{ $field==='description' ? 6 : 3 }}" required>{{ old($field,$page->{$field}) }}</textarea></div>@endforeach
<div class="form-grid"><div class="field"><label for="tasks_text">Tasks to discuss (one per line)</label><textarea id="tasks_text" name="tasks_text" rows="7" maxlength="3000" required>{{ old('tasks_text',implode("\n",$page->tasks)) }}</textarea></div><div class="field"><label for="extras_text">Additional requirements (one per line)</label><textarea id="extras_text" name="extras_text" rows="7" maxlength="3000">{{ old('extras_text',implode("\n",$page->extras)) }}</textarea></div></div>
<h2>Common questions</h2><p class="hint">Leave both fields empty to remove a question.</p>@for($i=0;$i<max(5,count($page->faqs));$i++)<div class="faq-editor"><div class="field"><label for="faqs-{{ $i }}-question">Question {{ $i+1 }}</label><input id="faqs-{{ $i }}-question" name="faqs[{{ $i }}][question]" value="{{ old('faqs.'.$i.'.question',$page->faqs[$i]['question'] ?? '') }}" maxlength="200"></div><div class="field"><label for="faqs-{{ $i }}-answer">Answer {{ $i+1 }}</label><textarea id="faqs-{{ $i }}-answer" name="faqs[{{ $i }}][answer]" rows="3" maxlength="1500">{{ old('faqs.'.$i.'.answer',$page->faqs[$i]['answer'] ?? '') }}</textarea></div></div>@endfor
<div class="button-row"><button class="button">Save page</button>@if($page->published)<a href="{{ route('services.service', $page->slug) }}">View public page ↗</a>@endif</div></form>
@endif
@endsection
