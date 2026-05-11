import { Component, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { EcommService } from '../../../services/ecomm.service';

@Component({
  selector: 'app-track-order',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './track-order.html',
  styleUrl: './track-order.css'
})
export class TrackOrderComponent {
  phone = signal('');
  order = signal<any>(null);
  loading = signal(false);
  error = signal('');

  constructor(private ecomm: EcommService) {}

  track() {
    if (!this.phone()) {
      this.error.set('Please enter your phone number');
      return;
    }
    
    this.loading.set(true);
    this.error.set('');
    this.order.set(null);

    this.ecomm.trackOrder(this.phone()).subscribe({
      next: (res) => {
        this.order.set(res);
        this.loading.set(false);
      },
      error: (err) => {
        this.error.set(err.error?.message || 'Order not found for this phone number.');
        this.loading.set(false);
      }
    });
  }
}
