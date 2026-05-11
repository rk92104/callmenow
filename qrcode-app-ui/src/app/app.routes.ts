import { Routes } from '@angular/router';
import { PersonListComponent } from './components/person-list/person-list';
import { PersonFormComponent } from './components/person-form/person-form';
import { LoginComponent } from './components/login/login';
import { DashboardComponent } from './components/dashboard/dashboard';
import { MainLayoutComponent } from './components/main-layout/main-layout';
import { ScanPageComponent } from './components/scan-page/scan-page';
import { ActivatePageComponent } from './components/activate-page/activate-page';
import { InventoryPageComponent } from './components/inventory-page/inventory-page';
import { LabelPrintPreviewComponent } from './components/label-print-preview/label-print-preview.component';
import { PublicShellComponent } from './components/marketing/public-shell.component';
import { HomePageComponent } from './components/marketing/home-page.component';
import { ProductPageComponent } from './components/marketing/product-page.component';
import { AboutPageComponent } from './components/marketing/about-page.component';
import { ContactPageComponent } from './components/marketing/contact-page.component';
import { LegalPageComponent } from './components/marketing/legal-page.component';
import { LegacyEditRedirectComponent } from './components/marketing/legacy-edit-redirect.component';
import { ShopPageComponent } from './components/marketing/shop-page.component';
import { OrdersListComponent } from './components/orders-list/orders-list.component';
import { TrackOrderComponent } from './components/marketing/track-order/track-order';
import { authGuard } from './guards/auth.guard';

export const routes: Routes = [
  {
    path: '',
    component: PublicShellComponent,
    children: [
      { path: '', pathMatch: 'full', component: HomePageComponent },
      { path: 'product', component: ProductPageComponent },
      { path: 'about', component: AboutPageComponent },
      { path: 'contact', component: ContactPageComponent },
      { path: 'privacy', component: LegalPageComponent, data: { doc: 'privacy' } },
      { path: 'terms', component: LegalPageComponent, data: { doc: 'terms' } },
      { path: 'refund', component: LegalPageComponent, data: { doc: 'refund' } },
      { path: 'shipping', component: LegalPageComponent, data: { doc: 'shipping' } },
      { path: 'shop', component: ShopPageComponent },
      { path: 'track', component: TrackOrderComponent },
    ],
  },
  { path: 'login', component: LoginComponent },
  { path: 'q/:publicId', component: ScanPageComponent },
  { path: 'activate/:publicId', component: ActivatePageComponent },
  {
    path: 'app',
    component: MainLayoutComponent,
    canActivate: [authGuard],
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'dashboard' },
      { path: 'dashboard', component: DashboardComponent },
      { path: 'inventory', component: InventoryPageComponent },
      { path: 'inventory/print-preview/:publicId', component: LabelPrintPreviewComponent },
      { path: 'contacts', component: PersonListComponent },
      { path: 'add', component: PersonFormComponent },
      { path: 'edit/:id', component: PersonFormComponent },
      { path: 'orders', component: OrdersListComponent },
    ],
  },
  { path: 'dashboard', redirectTo: 'app/dashboard', pathMatch: 'full' },
  { path: 'inventory', redirectTo: 'app/inventory', pathMatch: 'full' },
  { path: 'contacts', redirectTo: 'app/contacts', pathMatch: 'full' },
  { path: 'add', redirectTo: 'app/add', pathMatch: 'full' },
  { path: 'orders', redirectTo: 'app/orders', pathMatch: 'full' },
  { path: 'edit/:id', component: LegacyEditRedirectComponent },
  { path: '**', redirectTo: '' },
];

