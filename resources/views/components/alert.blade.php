<div id="alert-box"
     @class([
         'mb-4 px-4 py-3 rounded text-sm',
         'hidden' => ! session('success') && ! session('error'),
         'bg-green-100 text-green-800' => session('success'),
         'bg-red-100 text-red-800' => session('error'),
     ])>
    {{ session('success') ?? session('error') }}
</div>