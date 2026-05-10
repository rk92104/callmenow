import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-product-page',
  standalone: true,
  imports: [RouterLink],
  templateUrl: './product-page.component.html',
})
export class ProductPageComponent {}
