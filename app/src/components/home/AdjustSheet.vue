<template>
  <AnchoredSheet
    v-model:open="open"
    :label="t('home.adjust_sheet.label')"
    :anchor-to="anchorTo"
    align="right"
    width-class="lg:w-80"
  >
    <div class="space-y-6 pb-2">
      <div>
        <h2 class="text-meta font-medium tracking-wide text-text-muted uppercase">
          {{ t('home.adjust_sheet.order') }}
        </h2>
        <p class="mt-1 text-meta text-text-subtle">
          {{ t('home.adjust_sheet.order_hint') }}
        </p>

        <!--
          **The list is never rebuilt on a commit.** v-for with a stable key
          moves the existing DOM nodes rather than recreating them, so the
          handle somebody is holding survives a reorder and keeps its pointer
          capture and its focus. Keying on the index instead would rebuild every
          row on every move and drop the drag on the first one.
        -->
        <ul
          ref="list"
          class="mt-3"
          role="list"
          @pointermove="onPointerMove"
          @pointerup="onPointerUp"
          @pointercancel="onPointerUp"
        >
          <li
            v-for="key in order"
            :key="key"
            :data-block="key"
            class="flex items-center gap-3 rounded-control border border-border bg-surface-raised px-3 py-2 not-last:mb-2"
            :class="dragging === key ? 'opacity-60' : ''"
          >
            <button
              class="chip -ml-1 cursor-grab touch-none focus-visible:focus-ring"
              type="button"
              :aria-label="t('home.adjust_sheet.move', { block: t(`home.adjust_sheet.block.${key}`) })"
              :data-handle="key"
              @pointerdown="onPointerDown(key, $event)"
              @keydown="onHandleKeydown(key, $event)"
            >
              <Icon
                name="grip"
                class="size-5"
                aria-hidden="true"
              />
            </button>

            <span class="grow text-control text-text-strong">{{ t(`home.adjust_sheet.block.${key}`) }}</span>
          </li>
        </ul>
      </div>

      <div>
        <h2 class="text-meta font-medium tracking-wide text-text-muted uppercase">
          {{ t('home.adjust_sheet.count') }}
        </h2>

        <div
          class="mt-3 flex gap-1 rounded-control bg-surface-sunken p-1"
          role="group"
          :aria-label="t('home.adjust_sheet.count')"
        >
          <button
            v-for="count in previewCounts"
            :key="String(count)"
            class="grow rounded-control px-3 py-1.5 text-meta font-medium transition-colors focus-visible:focus-ring"
            :class="count === previewCount ? 'bg-surface-raised text-text-strong shadow-raised' : 'text-text-muted hover:text-text-strong'"
            type="button"
            :aria-pressed="count === previewCount"
            @click="emit('count', count)"
          >
            {{ count === 'all' ? t('home.adjust_sheet.count_all') : count }}
          </button>
        </div>

        <!--
          The sheet says what happens on a wide screen rather than leaving the
          artist to work out why the count did nothing: at lg Attention takes
          the main column and shows every item, because a task list in a 340px
          rail truncates its second lines.
        -->
        <p class="mt-2 text-meta text-text-subtle">
          {{ t('home.adjust_sheet.wide_note') }}
        </p>
      </div>
    </div>
  </AnchoredSheet>
</template>

<script setup lang="ts">
// Adjust: the block order and how many attention rows the phone previews.
//
// **Two settings and nothing else.** It must not grow a switch that turns a
// block off: a control that hides an unheld Saturday is a control that gets
// left off, and Home is where that would hurt most. If block visibility is ever
// wanted it is an argument to have on its own rather than a row to add here.
//
// The money period is deliberately absent. It was here for a round and came
// out: one value with two homes is two places to keep in step, and a "default"
// that differs from the current view is an artist who cannot work out why the
// block keeps changing back.
//
// This is AnchoredSheet's fourth caller, which is what decision 231 said would
// trigger the extraction: a bottom sheet below lg and a panel under the button
// at lg, measured at runtime because the button's position depends on the
// header's width.
import { nextTick, ref, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import AnchoredSheet from '@/components/ui/AnchoredSheet.vue'
import Icon from '@/components/ui/Icon.vue'
import { previewCounts, type BlockKey, type PreviewCount } from '@/lib/homeView'

const props = defineProps<{
  order: BlockKey[]
  previewCount: PreviewCount
  anchorTo?: HTMLElement | null
}>()

const emit = defineEmits<{
  order: [order: BlockKey[]]
  count: [count: PreviewCount]
}>()

const open = defineModel<boolean>('open', { required: true })

const { t } = useI18n()

const list = useTemplateRef<HTMLElement>('list')
const dragging = ref<BlockKey | null>(null)

/**
 * Move one block to a new index and tell the parent.
 *
 * The array is rebuilt rather than spliced in place so the parent's watcher
 * fires; the DOM is not, because v-for keyed on the block key moves nodes.
 */
function moveTo(key: BlockKey, index: number): void {
  const next = props.order.filter((block) => block !== key)

  next.splice(Math.min(Math.max(index, 0), next.length), 0, key)

  if (next.join() !== props.order.join()) {
    emit('order', next)
  }
}

/**
 * **preventDefault stops the drag becoming a text selection, and it also stops
 * the handle taking focus, so focus is given back by hand.**
 *
 * Without that second line the keyboard reorder is dead after any tap: the
 * handle never holds focus, so the arrow keys have nothing to act on. The
 * prototype found this the hard way and it is the one implementation note worth
 * carrying across.
 */
function onPointerDown(key: BlockKey, event: PointerEvent): void {
  event.preventDefault()

  const handle = event.currentTarget as HTMLElement

  handle.focus()
  handle.setPointerCapture(event.pointerId)
  dragging.value = key
}

function onPointerMove(event: PointerEvent): void {
  if (dragging.value === null || list.value === null) {
    return
  }

  const rows = [...list.value.children] as HTMLElement[]

  // Which row the pointer is over the second half of, which is the index the
  // held block should land at.
  let index = 0

  for (const row of rows) {
    if (row.dataset.block === dragging.value) {
      continue
    }

    const box = row.getBoundingClientRect()

    if (event.clientY > box.top + box.height / 2) {
      index += 1
    }
  }

  moveTo(dragging.value, index)
}

function onPointerUp(): void {
  dragging.value = null
}

/**
 * The keyboard half, which is not optional: a reorder that only works by
 * dragging is a reorder half the people cannot do.
 *
 * Focus is put back on the same handle after the move, because the node has
 * been moved in the DOM and a browser does not always keep focus through that.
 * It is the handle that moved rather than the one now in that position, so
 * holding an arrow key walks one block down the list rather than swapping two
 * back and forth.
 */
async function onHandleKeydown(key: BlockKey, event: KeyboardEvent): Promise<void> {
  if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') {
    return
  }

  const at = props.order.indexOf(key)
  const to = at + (event.key === 'ArrowDown' ? 1 : -1)

  if (to < 0 || to >= props.order.length) {
    return
  }

  event.preventDefault()
  moveTo(key, to)

  await nextTick()

  list.value?.querySelector<HTMLElement>(`[data-handle="${key}"]`)?.focus()
}
</script>
