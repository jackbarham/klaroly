import { afterEach, describe, expect, it } from 'vitest'
import { defineComponent, h } from 'vue'
import DataTable from '@/components/ui/DataTable.vue'
import { tableCellClasses, tableRowClasses } from '@/components/ui/table'
import { mount, unmount, type Mounted } from '@/lib/testMount'

// Two things break a table: a header that goes bold, and a hover that fills the
// row instead of recolouring the line under it. Both are tested here, along
// with the box that keeps a wide table from scrolling the page sideways.

const Host = defineComponent({
  setup() {
    return () => h(DataTable, {
      columns: [
        { key: 'client', label: 'Client' },
        { key: 'total', label: 'Total', align: 'end' as const },
      ],
    }, {
      default: () => h('tr', { class: tableRowClasses }, [
        h('td', { class: tableCellClasses }, 'Hannah Whitfield'),
        h('td', { class: `${tableCellClasses} text-right` }, '£840.00'),
      ]),
    })
  },
})

let mounted: Mounted | null = null

afterEach(() => {
  if (mounted) {
    unmount(mounted)
    mounted = null
  }
})

describe('a table', () => {
  it('scrolls inside its own box rather than widening the page', async () => {
    mounted = await mount(Host, '/')

    const container = mounted.host.querySelector('div')

    expect(container?.className).toContain('overflow-x-auto')
  })

  it('keeps its header cells regular weight and muted', async () => {
    mounted = await mount(Host, '/')

    const header = mounted.host.querySelector('thead tr')
    const cells = [...mounted.host.querySelectorAll('th')]

    expect(header?.className).toContain('text-text-muted')
    expect(cells).toHaveLength(2)

    for (const cell of cells) {
      expect(cell.className).toContain('font-normal')
      expect(cell.className).not.toContain('font-medium')
      expect(cell.getAttribute('scope')).toBe('col')
    }
  })

  it('right-aligns a column that asked for it', async () => {
    mounted = await mount(Host, '/')

    const cells = [...mounted.host.querySelectorAll('th')]

    expect(cells[0].className).not.toContain('text-right')
    expect(cells[1].className).toContain('text-right')
  })

  it('recolours a row divider on hover rather than filling the row', async () => {
    expect(tableRowClasses).toContain('border-b')
    expect(tableRowClasses).toContain('hover:border-accent')
    expect(tableRowClasses.split(' ').filter((name) => name.startsWith('hover:bg-'))).toEqual([])
  })

  it('leaves the last cell in a row flush with the edge', async () => {
    expect(tableCellClasses).toContain('last:pr-0')
  })
})
