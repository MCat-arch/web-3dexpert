@props(['type' => 'success'])

{{-- Replaces legacy's flow control, which was
     echo "<script>alert('...'); location.href='...'</script>"
     -- the reason edit.php and login.php both needed ob_start(). --}}
<div role="{{ $type === 'error' ? 'alert' : 'status' }}"
     @class([
         'mx-auto my-4 max-w-7xl rounded-lg border px-4 py-3 text-sm',
         'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' => $type === 'success',
         'border-red-500/30 bg-red-500/10 text-red-300' => $type === 'error',
     ])>
    {{ $slot }}
</div>
