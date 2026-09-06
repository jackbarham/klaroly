<template>
  <div class="space-y-8">
    <div class="space-y-4">
      <h3 class="text-sm font-medium text-text">
        The two colours
      </h3>
      <div class="flex flex-wrap items-center gap-2">
        <button
          class="chip focus-visible:focus-ring"
          type="button"
        >
          Call
        </button>
        <button
          class="chip focus-visible:focus-ring"
          type="button"
        >
          Text
        </button>
        <button
          class="chip focus-visible:focus-ring"
          type="button"
        >
          Email
        </button>
        <button
          class="chip focus-visible:focus-ring"
          type="button"
        >
          Maps
        </button>
        <button
          class="chip chip-danger focus-visible:focus-ring"
          type="button"
        >
          Delete
        </button>
      </div>
      <p class="text-xs text-text-muted">
        Two colours rather than three: the default is accent-subtle on
        accent-text and the danger chip is danger-subtle on danger-text. Both
        deepen on hover and neither lightens, which is the same rule the filled
        buttons follow. The hover fills are the only tokens this component
        needed, --accent-subtle-hover and --danger-subtle-hover, because the
        guide's hover rule covers something resting on nothing and something
        resting on the sunken grey, and a chip rests on a tint that is neither.
      </p>
    </div>

    <div class="space-y-4">
      <h3 class="text-sm font-medium text-text">
        As a link, which is half of what it is for
      </h3>
      <div class="flex flex-wrap items-center gap-2">
        <a
          class="chip focus-visible:focus-ring"
          href="tel:07700900461"
        >Call</a>
        <a
          class="chip focus-visible:focus-ring"
          href="mailto:someone@example.com"
        >Email</a>
      </div>
      <p class="text-xs text-text-muted">
        It is a class and not a component because half its uses are ordinary
        links that the operating system already knows what to do with, and half
        are buttons. A component wrapping either would be a switch on the
        element type where a class is nothing at all.
      </p>
    </div>

    <div class="space-y-4">
      <h3 class="text-sm font-medium text-text">
        The hit area
      </h3>
      <div class="flex flex-wrap items-center gap-2">
        <button
          class="chip outline outline-dashed outline-warning"
          type="button"
        >
          30px painted
        </button>
      </div>
      <p class="text-xs text-text-muted">
        The painted box is 30px, which is under the 44px minimum, so a
        pseudo-element takes the hit area to 44px without changing anything
        visible. That is the same trick the toggle uses, and the arithmetic is
        written out of --tap-target-min and the chip's own height so there is
        no number here that stops being true when either of them moves. The
        dashed outline above marks the painted edge only; try tapping above and
        below it.
      </p>
    </div>

    <div class="space-y-4">
      <h3 class="text-sm font-medium text-text">
        The grammar it completes
      </h3>
      <div class="flex flex-wrap items-center gap-2">
        <AppButton size="small">
          New booking
        </AppButton>
        <button
          class="chip focus-visible:focus-ring"
          type="button"
        >
          Edit
        </button>
        <button
          class="chip chip-danger focus-visible:focus-ring"
          type="button"
        >
          Delete
        </button>
      </div>
      <p class="text-xs text-text-muted">
        A filled accent button makes something new, a subtle accent chip acts on
        what is already there, and the danger chip is the destructive one. Three
        levels of noise for three levels of consequence. Note that the small
        AppButton is 40px and the style guide specifies 36px for a small button;
        the app has been 40px everywhere since the button was written, and which
        of the two is right is an open question rather than a Contacts one.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
// The chip, in both colours, as a button and as a link, and next to the button
// it is deliberately quieter than.
//
// It is a class in src/assets/app.css rather than a component, so this is the
// only page that can show it on its own.
</script>
