import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-xp-card',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './xp-card.component.html',
  styleUrl: './xp-card.component.css'
})
export class XpCardComponent {
  @Input() levelNum: number = 1;
  @Input() levelName: string = 'Iniciado Solitário';
  @Input() xp: number = 0;
  @Input() xpPercentage: number = 0;
}
