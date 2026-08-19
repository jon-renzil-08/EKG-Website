@push('scripts')
<script>
let searchTimer;

document.getElementById('searchInput').addEventListener('input', function() {
    const keyword = this.value;

    clearTimeout(searchTimer);
    searchTimer = setTimeout(function() {
        // Ganti icon jadi loading spinner
        document.getElementById('searchIcon').className = 'fas fa-spinner fa-spin text-muted';

        const url = new URL(window.location.href);
        url.searchParams.set('search', keyword);
        url.searchParams.set('page', 1);
        window.location.href = url.toString();
    }, 500);
});
</script>
@endpush