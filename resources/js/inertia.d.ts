import { Page } from '@inertiajs/inertia';

declare module 'vue' {
  interface ComponentCustomProperties {
    $page: Page;
  }
}
