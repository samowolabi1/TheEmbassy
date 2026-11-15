{{-- Applied before the page draws, so a collapsed sidebar doesn't flash open. --}}
<script>
    document.documentElement.classList.add('js');
    try { if (localStorage.getItem('side-collapsed') === '1') document.documentElement.classList.add('side-collapsed'); } catch (e) {}
</script>
