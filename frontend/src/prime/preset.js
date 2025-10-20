// PrimeVue PassThrough preset (unstyled) -> mapea a tus clases de main.css
const cls = (...c) => c.filter(Boolean).join(' ')

export default {
  // BUTTON
  button: {
    root: ({ props }) => ({
      class: cls(
        'btn',
        props.link && 'ghost',
        props.severity === 'danger'
          ? 'danger'
          : props.severity === 'success'
          ? 'success'
          : props.severity === 'warning'
          ? 'warn'
          : props.severity === 'secondary'
          ? ''
          : 'primary'
      ),
    }),
    label: { class: '' },
    icon: { class: '' },
  },

  // INPUTS
  inputtext: { root: { class: 'input' } },

  dropdown: {
    root: { class: 'input' },
    input: { class: '' },
    trigger: { class: '' },
    panel: { class: 'card' },
    header: { class: 'card-header' },
    filterInput: { class: 'input' },
    list: { class: '' },
    item: { class: '' },
    emptyMessage: { class: 'text-muted' },
  },

  calendar: {
    root: { class: 'input' },
    input: { class: '' },
    dropdownButton: { class: 'btn' },
    panel: { class: 'card' },
    header: { class: 'card-header' },
    table: { class: 'table' },
    day: { class: '' },
    month: { class: '' },
    year: { class: '' },
  },

  // DIALOG
  dialog: {
    mask: { class: 'modal' },
    root: { class: 'box card' },
    header: { class: 'card-header' },
    content: { class: '' },
    footer: { class: 'flex gap-12 justify-between mt-12' },
    closeButton: { class: 'btn ghost' },
  },

  // DATATABLE
  datatable: {
    root: { class: 'card' },
    table: { class: 'table' },
    header: { class: 'card-header' },
    footer: { class: 'card-header' },
    loadingOverlay: { class: 'glass' },
    paginatorTop: { class: 'mt-12' },
    paginatorBottom: { class: 'mt-12' },
    row: { class: '' },
    rowGroupHeader: { class: '' },
    rowGroupFooter: { class: '' },
    emptyMessage: { class: 'text-muted' },
  },
  column: {
    headerCell: { class: '' },
    bodyCell: { class: '' },
    footerCell: { class: '' },
  },

  // PAGINATOR
  paginator: {
    root: { class: 'flex items-center gap-12' },
    firstPageButton: { class: 'btn' },
    prevPageButton: { class: 'btn' },
    nextPageButton: { class: 'btn' },
    lastPageButton: { class: 'btn' },
    pageButton: ({ context }) => ({ class: cls('btn', context.active && 'primary') }),
    current: { class: 'text-muted' },
    dropdown: { class: 'input' },
    input: { class: 'input' },
  },

  // MENU
  menu: {
    root: { class: 'p-menu' },
    menu: { class: '' },
    submenuHeader: { class: 'text-muted' },
    content: { class: '' },
    item: { class: 'user-menu-item' },
    action: { class: 'btn ghost' },
    icon: { class: '' },
    label: { class: '' },
    separator: { class: 'mt-12' },
  },

  // TOOLBAR
  toolbar: {
    root: { class: 'card' },
    start: { class: 'flex gap-12' },
    center: { class: 'flex' },
    end: { class: 'flex gap-12' },
  },

  // TOAST
  toast: {
    root: { class: 'container' },
    container: { class: 'card mt-12' },
    content: { class: 'flex items-center gap-12' },
    message: { class: '' },
    summary: { class: 'card-title' },
    detail: { class: 'text-muted' },
    closeButton: { class: 'btn ghost' },
  },
}
