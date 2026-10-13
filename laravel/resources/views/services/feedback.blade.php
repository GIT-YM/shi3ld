@if(session('message'))<div class="notice" role="status">{{ session('message') }}</div>@endif
@if($errors->any())<div class="errors" role="alert" tabindex="-1" id="error-summary"><strong>Please check the following details.</strong><ul>@foreach($errors->messages() as $field=>$messages)<li><a href="#{{ str_replace('.', '-', $field) }}">{{ $messages[0] }}</a></li>@endforeach</ul></div>@endif
