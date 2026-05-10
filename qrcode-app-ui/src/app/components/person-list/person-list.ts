import { Component, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { PersonService } from '../../services/person.service';
import { Person } from '../../models/person.model';

import { FormsModule } from '@angular/forms';
// ...
@Component({
    selector: 'app-person-list',
    standalone: true,
    imports: [CommonModule, RouterLink, FormsModule],
    templateUrl: './person-list.html',
    styleUrl: './person-list.css'
})
export class PersonListComponent implements OnInit {
    persons = signal<Person[]>([]);
    loading = signal(true);
    showQRModal = signal(false);
    qrCodeImage = signal('');
    qrPersonName = signal('');
    qrScanUrl = signal('');
    qrPublicId = signal('');
    showDeleteConfirm = signal(false);
    deletePersonId = signal<number | null>(null);
    deletePersonName = signal('');
    notification = signal<{ message: string; type: 'success' | 'error' } | null>(null);

    // Search and Pagination
    searchTerm = signal('');
    currentPage = signal(1);
    pageSize = 10;

    filteredPersons = computed(() => {
        const term = this.searchTerm().toLowerCase();
        const all = this.persons();
        if (!term) return all;
        return all.filter(p =>
            p.name.toLowerCase().includes(term) ||
            p.phoneNumber.includes(term)
        );
    });

    paginatedPersons = computed(() => {
        const filtered = this.filteredPersons();
        const start = (this.currentPage() - 1) * this.pageSize;
        return filtered.slice(start, start + this.pageSize);
    });

    totalPages = computed(() => {
        const count = this.filteredPersons().length;
        return Math.max(1, Math.ceil(count / this.pageSize));
    });

    totalFiltered = computed(() => this.filteredPersons().length);

    constructor(
        private readonly personService: PersonService
    ) { }



    ngOnInit(): void {
        this.loadPersons();
    }

    loadPersons(): void {
        this.loading.set(true);
        this.personService.getAll().subscribe({
            next: (data) => {
                this.persons.set(data);
                this.loading.set(false);
            },
            error: (err) => {
                console.error('Error loading persons:', err);
                this.loading.set(false);
                this.showNotification('Failed to load data. Make sure the API is running.', 'error');
            }
        });
    }

    confirmDelete(person: Person): void {
        this.deletePersonId.set(person.id);
        this.deletePersonName.set(person.name);
        this.showDeleteConfirm.set(true);
    }

    cancelDelete(): void {
        this.showDeleteConfirm.set(false);
        this.deletePersonId.set(null);
        this.deletePersonName.set('');
    }

    deletePerson(): void {
        const id = this.deletePersonId();
        if (id === null) return;

        this.personService.delete(id).subscribe({
            next: () => {
                this.showDeleteConfirm.set(false);
                this.showNotification('Person deleted successfully!', 'success');
                this.loadPersons();
            },
            error: (err) => {
                console.error('Error deleting person:', err);
                this.showNotification('Failed to delete person.', 'error');
                this.showDeleteConfirm.set(false);
            }
        });
    }

    viewQRCode(person: Person): void {
        this.qrPersonName.set(person.name);
        this.qrScanUrl.set('');
        this.qrPublicId.set('');
        this.personService.getQRCodeBase64(person.id).subscribe({
            next: (data) => {
                this.qrCodeImage.set(data.qrCodeImage);
                this.qrScanUrl.set(data.qrText ?? '');
                this.qrPublicId.set(data.publicId ?? '');
                this.showQRModal.set(true);
            },
            error: (err) => {
                console.error('Error generating QR code:', err);
                this.showNotification(
                    'No active CallMeNow sticker is linked to this owner. Activate a tag from Inventory first.',
                    'error'
                );
            }
        });
    }

    closeQRModal(): void {
        this.showQRModal.set(false);
        this.qrCodeImage.set('');
        this.qrPersonName.set('');
        this.qrScanUrl.set('');
        this.qrPublicId.set('');
    }

    onSearchChange(): void {
        this.currentPage.set(1);
    }

    goToPage(page: number): void {
        if (page >= 1 && page <= this.totalPages()) {
            this.currentPage.set(page);
        }
    }

    getPages(): number[] {
        return Array.from({ length: this.totalPages() }, (_, i) => i + 1);
    }

    showNotification(message: string, type: 'success' | 'error'): void {
        this.notification.set({ message, type });
        setTimeout(() => this.notification.set(null), 4000);
    }
}
