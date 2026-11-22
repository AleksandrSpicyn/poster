<template>
    <span class="studio-brand"
        ><span class="studio-brand-mark" aria-hidden="true"><i /><i /><i /></span><strong>Poster</strong></span
    >
</template>
<style scoped>
.studio-brand {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: var(--foreground);
    font-size: 23px;
    line-height: 1;
}
.studio-brand-mark {
    display: flex;
    gap: 2px;
    padding-inline: 5px;
}
.studio-brand-mark i {
    width: 5px;
    height: 20px;
    transform: skew(-24deg);
    background: var(--ring);
}
:global([data-state='collapsed'][data-slot='sidebar'] .studio-brand strong) {
    display: none;
}
</style>
