// PrimeVue + Aura con primario negro y modo oscuro por clase
import PrimeVue from 'primevue/config'
import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'
import Tooltip from 'primevue/tooltip'
import { definePreset } from '@primeuix/themes'
import Aura from '@primeuix/themes/aura'
import 'primeicons/primeicons.css'

// Componentes globales (ajusta a lo que realmente uses)
import Accordion from 'primevue/accordion'
import AccordionTab from 'primevue/accordiontab'
import AutoComplete from 'primevue/autocomplete'
import Avatar from 'primevue/avatar'
import AvatarGroup from 'primevue/avatargroup'
import Badge from 'primevue/badge'
import Button from 'primevue/button'
import Calendar from 'primevue/calendar'
import Card from 'primevue/card'
import Carousel from 'primevue/carousel'
import Checkbox from 'primevue/checkbox'
import Chip from 'primevue/chip'
import Chips from 'primevue/chips'
import ColorPicker from 'primevue/colorpicker'
import ConfirmDialog from 'primevue/confirmdialog'
import ConfirmPopup from 'primevue/confirmpopup'
import ContextMenu from 'primevue/contextmenu'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import /* !verifica versión */ Editor from 'primevue/editor'
import Fieldset from 'primevue/fieldset'
import FileUpload from 'primevue/fileupload'
import FloatLabel from 'primevue/floatlabel'
import Image from 'primevue/image'
import InlineMessage from 'primevue/inlinemessage'
import InputMask from 'primevue/inputmask'
import InputNumber from 'primevue/inputnumber'
import InputOtp from 'primevue/inputotp'
import InputSwitch from 'primevue/inputswitch'
import InputText from 'primevue/inputtext'
import Knob from 'primevue/knob'
import Listbox from 'primevue/listbox'
import Menu from 'primevue/menu'
import Menubar from 'primevue/menubar'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import Popover from 'primevue/popover' // reemplaza OverlayPanel
import Panel from 'primevue/panel'
import Password from 'primevue/password'
import ProgressBar from 'primevue/progressbar'
import ProgressSpinner from 'primevue/progressspinner'
import RadioButton from 'primevue/radiobutton'
import Rating from 'primevue/rating'
import ScrollPanel from 'primevue/scrollpanel'
import ScrollTop from 'primevue/scrolltop'
import SelectButton from 'primevue/selectbutton'
import Sidebar from 'primevue/sidebar'
import Skeleton from 'primevue/skeleton'
import Slider from 'primevue/slider'
import SplitButton from 'primevue/splitbutton'
import Steps from 'primevue/steps'
import TabMenu from 'primevue/tabmenu'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import Timeline from 'primevue/timeline'
import Toast from 'primevue/toast'
import ToggleButton from 'primevue/togglebutton'
import Toolbar from 'primevue/toolbar'
import Tree from 'primevue/tree'
import TreeTable from 'primevue/treetable'
import Divider from 'primevue/divider'

// preset Aura con primario negro
const BlackPrimaryAura = definePreset(Aura, {
  semantic: {
    primary: {
      50:  '{neutral.50}',
      100: '{neutral.100}',
      200: '{neutral.200}',
      300: '{neutral.300}',
      400: '{neutral.400}',
      500: '{neutral.900}', // usa '{neutral.950}' si quieres negro absoluto
      600: '{neutral.900}',
      700: '{neutral.900}',
      800: '{neutral.950}',
      900: '{neutral.950}',
      950: '{neutral.950}'
    }
  }
})

export default function setupPrimeVue(app) {
  app.use(PrimeVue, {
    ripple: true,
    theme: {
      preset: BlackPrimaryAura,
      options: {
        prefix: 'p',
        cssLayer: false,
        darkModeSelector: '.app-dark'
      }
    }
  })

  app.use(ToastService)
  app.use(ConfirmationService)

  app.component('Accordion', Accordion)
  app.component('AccordionTab', AccordionTab)
  app.component('AutoComplete', AutoComplete)
  app.component('Avatar', Avatar)
  app.component('AvatarGroup', AvatarGroup)
  app.component('Badge', Badge)
  app.component('Button', Button)
  app.component('Calendar', Calendar)
  app.component('Card', Card)
  app.component('Carousel', Carousel)
  app.component('Checkbox', Checkbox)
  app.component('Chip', Chip)
  app.component('Chips', Chips)
  app.component('ColorPicker', ColorPicker)
  app.component('ConfirmDialog', ConfirmDialog)
  app.component('ConfirmPopup', ConfirmPopup)
  app.component('ContextMenu', ContextMenu)
  app.component('DataTable', DataTable)
  app.component('Column', Column)
  app.component('Dialog', Dialog)
  app.component('Dropdown', Dropdown)
  app.component('Editor', Editor)
  app.component('Fieldset', Fieldset)
  app.component('FileUpload', FileUpload)
  app.component('FloatLabel', FloatLabel)
  app.component('Image', Image)
  app.component('InlineMessage', InlineMessage)
  app.component('InputMask', InputMask)
  app.component('InputNumber', InputNumber)
  app.component('InputOtp', InputOtp)
  app.component('InputSwitch', InputSwitch)
  app.component('InputText', InputText)
  app.component('Knob', Knob)
  app.component('Listbox', Listbox)
  app.component('Menu', Menu)
  app.component('Menubar', Menubar)
  app.component('Message', Message)
  app.component('MultiSelect', MultiSelect)
  app.component('Popover', Popover)
  app.component('Panel', Panel)
  app.component('Password', Password)
  app.component('ProgressBar', ProgressBar)
  app.component('ProgressSpinner', ProgressSpinner)
  app.component('RadioButton', RadioButton)
  app.component('Rating', Rating)
  app.component('ScrollPanel', ScrollPanel)
  app.component('ScrollTop', ScrollTop)
  app.component('SelectButton', SelectButton)
  app.component('Sidebar', Sidebar)
  app.component('Skeleton', Skeleton)
  app.component('Slider', Slider)
  app.component('SplitButton', SplitButton)
  app.component('Steps', Steps)
  app.component('TabMenu', TabMenu)
  app.component('TabView', TabView)
  app.component('TabPanel', TabPanel)
  app.component('Tag', Tag)
  app.component('Textarea', Textarea)
  app.component('Timeline', Timeline)
  app.component('Toast', Toast)
  app.component('ToggleButton', ToggleButton)
  app.component('Toolbar', Toolbar)
  app.component('Tree', Tree)
  app.component('TreeTable', TreeTable)
  app.component('Divider', Divider)

  app.directive('tooltip', Tooltip)
}
