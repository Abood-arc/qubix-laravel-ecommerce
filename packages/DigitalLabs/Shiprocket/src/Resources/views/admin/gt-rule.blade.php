{{--
    The parcel size settings use Laravel's gt:<number> rule ("more than 0.5"). The admin's
    form validator (vee-validate) has no such rule and stops validating the whole form on a
    rule it does not know, so its browser twin is defined here, before the app mounts.
--}}
<script type="module">
    window.defineRule('gt', (value, [limit]) => {
        if (value === undefined || value === null || String(value).trim() === '') {
            return true;
        }

        return Number(value) > Number(limit);
    });
</script>
