import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { PersonService } from '../../services/person.service';
import { Person } from '../../models/person.model';

@Component({
    selector: 'app-person-view',
    standalone: true,
    imports: [CommonModule, RouterLink],
    templateUrl: './person-view.html',
    styleUrl: './person-view.css'
})
export class PersonViewComponent implements OnInit {
    person = signal<Person | null>(null);
    loading = signal(true);
    error = signal(false);

    constructor(
        private readonly personService: PersonService,
        private readonly route: ActivatedRoute
    ) { }

    ngOnInit(): void {
        const id = this.route.snapshot.paramMap.get('id');
        if (id) {
            this.loadPerson(+id);
        }
    }

    loadPerson(id: number): void {
        this.personService.getById(id).subscribe({
            next: (data) => {
                this.person.set(data);
                this.loading.set(false);
            },
            error: () => {
                this.loading.set(false);
                this.error.set(true);
            }
        });
    }
}
