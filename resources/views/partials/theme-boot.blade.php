<script>
    (function () {
        try {
            var k = 'phppgadmin.theme';
            var s = null;
            try {
                s = localStorage.getItem(k);
            } catch (e) {}
            if (s !== 'light' && s !== 'dark' && s !== 'system') {
                s = 'system';
            }
            var dark =
                s === 'dark' ||
                (s === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
        } catch (e) {}
    })();
</script>
