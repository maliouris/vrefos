import {cva, type VariantProps} from 'class-variance-authority'

export { default as MenuItem } from './MenuItem.vue'

export const menuItemsVariants = cva(
  'flex items-center gap-3 rounded-lg px-3 py-2 transition-all hover:text-primary',
  {
    variants: {
        default: '',
        active: 'bg-muted text-primary'
    },
    defaultVariants: {
      type: 'default',
    },
  },
)

export type MenuItemsVariants = VariantProps<typeof menuItemsVariants>
