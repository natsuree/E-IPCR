{{-- MMSU seal from public/images/mmsu-seal.png, or a lettered placeholder until the file is added. --}}
@if (is_file(public_path('images/mmsu-seal.png')))
    <img src="{{ asset('images/mmsu-seal.png') }}" alt="Mariano Marcos State University seal" class="seal">
@else
    <div class="seal" role="img" aria-label="Mariano Marcos State University seal">MMSU</div>
@endif
