import { onBeforeUnmount, onMounted, ref, useTemplateRef, type Ref } from 'vue'

// The two measurements a list-beside-detail screen makes, written once for the
// contacts and enquiries screens.
//
// Both are about where things come to rest rather than how big anything is.
// The band headings under the filter bar are sticky and have to rest under the
// bar rather than behind it, so the bar's height is measured and handed down
// as --stick-offset; it is measured rather than written down because the bar
// wraps at a narrow width and gets taller when it does. The detail card is
// sticky beside the list and stops being sticky when it does not fit, because
// a sticky element taller than the window pins its own top and puts its own
// bottom out of reach. That one is a boolean and never a height: nothing here
// is ever given a size worked out from the viewport, which is what decision
// 201 rules out and the thing that fights Capacitor.
//
// A ResizeObserver rather than a watcher, because the bar's height moves for
// reasons the screen does not initiate: the button wrapping under the field at
// a narrow width, and the browser's own text size. The window listener is
// there because an observer sees the card change and not the window, and the
// window is half of the comparison.
//
// The caller puts ref="bar" on its filter bar and ref="detailCol" on the
// detail column. The two names are fixed here so the two screens cannot wire
// them two ways.
export function useSplitList(): { barHeight: Ref<number>, detailFits: Ref<boolean> } {
  const bar = useTemplateRef<{ $el: HTMLElement } | null>('bar')
  const barHeight = ref(0)

  const detailCol = useTemplateRef<HTMLElement>('detailCol')
  const detailFits = ref(true)

  let sizes: ResizeObserver | null = null

  function measure(): void {
    const barElement = bar.value?.$el

    if (barElement instanceof HTMLElement) {
      barHeight.value = Math.round(barElement.getBoundingClientRect().height)
    }

    const card = detailCol.value

    if (card) {
      // offsetHeight rather than the bounding rectangle, because a sticky
      // element that is currently pinned still reports its whole height here
      // and the answer must not depend on where the page happens to be
      // scrolled to. Toggling stickiness does not change this number, so the
      // two cannot chase each other.
      detailFits.value = card.offsetHeight <= window.innerHeight
    }
  }

  onMounted(() => {
    measure()

    sizes = new ResizeObserver(measure)

    const barElement = bar.value?.$el

    if (barElement instanceof HTMLElement) {
      sizes.observe(barElement)
    }

    if (detailCol.value) {
      sizes.observe(detailCol.value)
    }

    window.addEventListener('resize', measure, { passive: true })
  })

  onBeforeUnmount(() => {
    sizes?.disconnect()
    sizes = null
    window.removeEventListener('resize', measure)
  })

  return { barHeight, detailFits }
}
