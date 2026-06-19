import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-attributes-card',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './attributes-card.component.html',
  styleUrl: './attributes-card.component.css'
})
export class AttributesCardComponent {
  @Input() stoicism: number = 0;
  @Input() jung: number = 0;
  @Input() sati: number = 0;
  @Input() campbell: number = 0;
}
