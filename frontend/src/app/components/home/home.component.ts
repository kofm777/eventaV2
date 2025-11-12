import { Component } from '@angular/core';
import { RouterModule } from '@angular/router';
import { trigger, transition, style, animate } from '@angular/animations';
import {NgOptimizedImage} from "@angular/common";

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [RouterModule, NgOptimizedImage],
  templateUrl: './home.component.html',
  styleUrls: ['./home.component.scss'],
  animations: [
    trigger('fadeIn', [
      transition(':enter', [
        style({ opacity: 0, transform: 'translateY(40px)' }),
        animate('900ms cubic-bezier(.77,0,.18,1)', style({ opacity: 1, transform: 'none' }))
      ])
    ])
  ]
})
export class HomeComponent {
  musicPlaying = false;
  currentYear = new Date().getFullYear();
  toggleMusic() {
    this.musicPlaying = !this.musicPlaying;
    // TODO: Add logic to play/pause background music
  }
}
