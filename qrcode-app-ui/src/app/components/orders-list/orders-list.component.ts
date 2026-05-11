import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';


@Component({
  selector: 'app-orders-list',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="orders-page">
      <header class="page-header">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
          <div>
            <h1 class="page-title">Bookings (Sticker Orders)</h1>
            <p class="page-subtitle">View and manage physical sticker purchases.</p>
          </div>
          <div class="header-actions">
            <button class="btn btn-secondary" (click)="printAllLabels()" [disabled]="orders().length === 0">
              🖨️ Print All Labels
            </button>
            <button class="btn btn-primary" (click)="fetchOrders()">🔄 Refresh</button>
          </div>
        </div>
      </header>

      <div class="table-card">
        @if (loading()) {
          <div class="loading-state">Loading bookings...</div>
        } @else if (orders().length === 0) {
          <div class="empty-state">No bookings found yet.</div>
        } @else {
          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Order ID</th>
                  <th>Date</th>
                  <th>Customer</th>
                  <th>Contact</th>
                  <th>Product</th>
                  <th>Amount</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @for (o of orders(); track o.id) {
                  <tr (click)="openOrderDetails(o)">
                    <td class="font-mono">#{{o.id}}</td>
                    <td>{{o.createdAtUtc | date:'MMM d, y, h:mm a'}}</td>
                    <td class="fw-600">{{o.customerName}}</td>
                    <td>
                      <div>{{o.customerPhone}}</div>
                      <div class="text-small muted">{{o.city}}, {{o.pincode}}</div>
                    </td>
                    <td>{{o.productName}}</td>
                    <td class="fw-600">₹{{o.amount}}</td>
                    <td><span class="status-badge" [class]="(o.status || 'pending').toLowerCase()">{{o.status || 'Pending'}}</span></td>
                  </tr>
                }
              </tbody>
            </table>
          </div>
        }
      </div>

      <!-- Customer Detail Modal -->
      @if (selectedOrder()) {
        <div class="modal-overlay" (click)="closeModal()">
          <div class="modal-content" (click)="$event.stopPropagation()">
            <div class="modal-header">
              <h3 class="modal-title">Order #{{selectedOrder().id}} Details</h3>
              <button class="modal-close" (click)="closeModal()">✕</button>
            </div>
            <div class="modal-body">
              <div class="detail-grid">
                <div class="detail-group">
                  <span class="detail-label">Customer Name</span>
                  <p class="detail-value">{{selectedOrder().customerName}}</p>
                </div>
                <div class="detail-group">
                  <span class="detail-label">Phone Number</span>
                  <p class="detail-value">{{selectedOrder().customerPhone}}</p>
                </div>
                <div class="detail-group" style="grid-column: 1 / -1;">
                  <span class="detail-label">Complete Shipping Address</span>
                  <p class="detail-address">{{selectedOrder().shippingAddress}}</p>
                  <p class="detail-value" style="margin-top: 8px;">{{selectedOrder().city}} - {{selectedOrder().pincode}}</p>
                </div>
                <div class="detail-group">
                  <span class="detail-label">Product Ordered</span>
                  <p class="detail-value">{{selectedOrder().productName}} ({{selectedOrder().productId}})</p>
                </div>
                <div class="detail-group">
                  <span class="detail-label">Total Amount</span>
                  <p class="detail-value">₹{{selectedOrder().amount}}</p>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <div class="action-group">
                <select #statusSelect class="status-select" [value]="selectedOrder().status || 'Pending'" (change)="updateStatus(selectedOrder().id, statusSelect.value)">
                  <option value="Pending">Pending</option>
                  <option value="Shipped">Shipped</option>
                  <option value="Delivered">Delivered</option>
                  <option value="Cancelled">Cancelled</option>
                </select>
                <button class="btn btn-primary" (click)="printSingleRow(selectedOrder())">🖨️ Shipping Label</button>
                @if (selectedOrder().assignedPublicId) {
                  <button class="btn btn-secondary" (click)="printSticker(selectedOrder())" style="background: #ef9523; color: white; border: none;">
                    🎯 Print Sticker
                  </button>
                }
              </div>
              <button class="btn btn-secondary" (click)="closeModal()">Close</button>
            </div>
          </div>
        </div>
      }
    </div>
  `,
  styles: [`
    .orders-page { max-width: 1200px; margin: 0 auto; animation: fade 0.3s ease; }
    @keyframes fade { from { opacity: 0; } to { opacity: 1; } }
    .page-header { margin-bottom: 24px; }
    .page-title { font-size: 1.5rem; font-weight: 800; color: var(--cmn-heading); margin: 0 0 4px 0; letter-spacing: -0.02em; }
    .page-subtitle { color: var(--cmn-text-muted); font-size: 0.95rem; margin: 0; }
    
    .table-card { background: var(--cmn-card-bg-solid, white); border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid var(--cmn-card-border, #e2e8f0); overflow: hidden; }
    
    .table-responsive { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
    .data-table th, .data-table td { padding: 16px 20px; text-align: left; border-bottom: 1px solid var(--cmn-card-border, #e2e8f0); color: var(--cmn-text); }
    .data-table th { background: rgba(99,102,241,0.04); font-weight: 700; color: var(--cmn-text-muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; }
    
    .font-mono { font-family: ui-monospace, monospace; color: #6366f1; font-weight: 600; }
    .fw-600 { font-weight: 600; }
    .text-small { font-size: 0.8rem; }
    .muted { color: var(--cmn-text-muted); }
    
     .status-badge { padding: 6px 12px; border-radius: 99px; font-size: 0.75rem; font-weight: 700; background: #e2e8f0; color: #475569; text-transform: uppercase; letter-spacing: 0.02em; cursor: pointer; transition: all 0.2s;}
    .status-badge:hover { filter: brightness(0.95); transform: translateY(-1px); }
    .status-badge.pending { background: #fef3c7; color: #d97706; }
    .status-badge.shipped { background: #dbeafe; color: #1e3a8a; }
    .status-badge.completed, .status-badge.paid { background: #d1fae5; color: #059669; }

    .loading-state, .empty-state { padding: 64px; text-align: center; color: var(--cmn-text-muted); font-size: 1.1rem; }

    /* Modal Styles */
    .modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.4); backdrop-filter: blur(4px); z-index: 1000; display: flex; align-items: center; justify-content: center; animation: fadeIn 0.2s ease-out; padding: 20px;}
    .modal-content { background: white; border-radius: 20px; width: 100%; max-width: 650px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideUp { from { opacity: 0; transform: translateY(20px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }
    
    .modal-header { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #1aa4b8, #0d4f5a); color: white; border-top-left-radius: 20px; border-top-right-radius: 20px;}
    .modal-title { margin: 0; font-size: 1.3rem; font-family: 'Fraunces', serif; font-weight: 700; }
    .modal-close { background: rgba(255,255,255,0.2); border: none; color: white; width: 32px; height: 32px; border-radius: 50%; font-size: 1.2rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
    .modal-close:hover { background: rgba(255,255,255,0.3); }
    
    .modal-body { padding: 24px; }
    .detail-grid { display: grid; gap: 20px; grid-template-columns: 1fr; }
    @media (min-width: 500px) { .detail-grid { grid-template-columns: 1fr 1fr; } }
    .detail-group { background: #f8fafc; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0; }
    .detail-label { display: block; font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
    .detail-value { font-size: 1.05rem; font-weight: 600; color: #1e293b; margin: 0; }
    .detail-address { font-size: 0.95rem; line-height: 1.5; color: #334155; margin: 0; white-space: pre-wrap; }
    
    .modal-footer { padding: 20px 24px; border-top: 1px solid #f1f5f9; background: #f8fafc; display: flex; justify-content: space-between; align-items: center; border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; flex-wrap: wrap; gap: 12px;}
    
    .action-group { display: flex; gap: 12px; align-items: center; }
    .status-select { padding: 8px 16px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 0.95rem; font-weight: 600; color: #334155; background: white; cursor: pointer; }
    .status-select:focus { outline: none; border-color: #0d4f5a; box-shadow: 0 0 0 3px rgba(13,79,90,0.1); }
    
    .btn { padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; cursor: pointer; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px;}
    .btn-primary { background: linear-gradient(135deg, #1aa4b8, #0d4f5a); color: white; box-shadow: 0 4px 12px rgba(13,79,90,0.2); }
    .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(13,79,90,0.3); }
    .btn-secondary { background: white; color: #334155; border: 1px solid #cbd5e1; }
    .btn-secondary:hover { background: #f1f5f9; }

    /* For table row click */
    .data-table tbody tr { cursor: pointer; transition: background 0.15s; }
    .data-table tbody tr:hover { background: #f8fafc; }

    .header-actions { display: flex; gap: 12px; margin-top: 16px;}
  `]
})
export class OrdersListComponent implements OnInit {
  private http = inject(HttpClient);
  orders = signal<any[]>([]);
  loading = signal(true);
  
  selectedOrder = signal<any | null>(null);

  ngOnInit() {
    this.fetchOrders();
  }

  fetchOrders() {
    this.loading.set(true);
    this.http.get<any[]>(`/api/ecomm/orders`).subscribe({
      next: (data) => {
        this.orders.set(data);
        this.loading.set(false);
      },
      error: () => this.loading.set(false)
    });
  }

  openOrderDetails(order: any) {
    this.selectedOrder.set(order);
  }

  closeModal() {
    this.selectedOrder.set(null);
  }

  updateStatus(orderId: number, status: string) {
    if (!status) return;
    this.http.put(`/api/ecomm/orders/${orderId}/status`, { status }).subscribe({
      next: () => {
        this.orders.update(ords => ords.map(o => o.id === orderId ? { ...o, status } : o));
        const currentSelected = this.selectedOrder();
        if (currentSelected && currentSelected.id === orderId) {
            this.selectedOrder.set({ ...currentSelected, status });
        }
      },
      error: (err) => alert('Failed to update status')
    });
  }

  printSingleRow(order: any) {
    const printWindow = window.open('', '_blank');
    if (!printWindow) return;

    const html = `
      <html>
      <head>
        <title>Print Shipping Label - #${order.id}</title>
        <style>
          body { font-family: sans-serif; padding: 20px; }
          .label { border: 1px solid #000; padding: 20px; width: 400px; margin-bottom: 20px; border-radius: 8px;}
          h2 { margin-top: 0; }
          .muted { color: #555; font-size: 0.9em; }
        </style>
      </head>
      <body onload="window.print(); window.close();">
        <div class="label">
          <h2>Shipping Label</h2>
          <p><strong>To:</strong> ${order.customerName}</p>
          <p><strong>Phone:</strong> ${order.customerPhone}</p>
          <p><strong>Address:</strong><br/>${order.shippingAddress.replace(/\\n/g, '<br/>')}</p>
          <p><strong>City/Pincode:</strong> ${order.city}, ${order.pincode}</p>
          <hr/>
          <p class="muted"><strong>Product:</strong> ${order.productName} (ID: ${order.productId})</p>
          <p class="muted"><strong>Order #${order.id}</strong></p>
        </div>
      </body>
      </html>
    `;
    printWindow.document.write(html);
    printWindow.document.close();
  }

  printSticker(order: any) {
    const url = `/api/ecomm/orders/${order.id}/print`;
    window.open(url, '_blank');
  }

  printAllLabels() {
    const printWindow = window.open('', '_blank');
    if (!printWindow) return;

    let labelsHtml = this.orders().map(order => `
        <div class="label" style="page-break-inside: avoid;">
          <h2>Shipping Label</h2>
          <p><strong>To:</strong> ${order.customerName}</p>
          <p><strong>Phone:</strong> ${order.customerPhone}</p>
          <p><strong>Address:</strong><br/>${order.shippingAddress.replace(/\\n/g, '<br/>')}</p>
          <p><strong>City/Pincode:</strong> ${order.city}, ${order.pincode}</p>
          <hr/>
          <p class="muted"><strong>Product:</strong> ${order.productName}</p>
          <p class="muted"><strong>Order #${order.id}</strong></p>
        </div>
    `).join('');

    const html = `
      <html>
      <head>
        <title>Print All Labels</title>
        <style>
          body { font-family: sans-serif; padding: 20px; }
          .label { border: 1px dashed #000; padding: 20px; width: 45%; display: inline-block; margin: 10px; vertical-align: top; box-sizing: border-box; border-radius: 8px;}
          h2 { margin-top: 0; }
          .muted { color: #555; font-size: 0.9em; }
        </style>
      </head>
      <body onload="window.print(); window.close();">
        ${labelsHtml}
      </body>
      </html>
    `;
    printWindow.document.write(html);
    printWindow.document.close();
  }
}
