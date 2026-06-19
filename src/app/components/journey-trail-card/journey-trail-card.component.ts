import { Component, Input, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';
import { JourneyStage } from '../../services/journey';

@Component({
  selector: 'app-journey-trail-card',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './journey-trail-card.component.html',
  styleUrl: './journey-trail-card.component.css'
})
export class JourneyTrailCardComponent {
  @Input() totalJourneyDays: number[] = Array.from({ length: 21 }, (_, i) => i + 1);
  @Input() currentDay: number = 1;
  @Input() history: JourneyStage[] = [];

  @Output() selectStage = new EventEmitter<JourneyStage>();

  public obterEstagioDoDia(day: number): JourneyStage | undefined {
    return this.history.find(stage => stage.current_day === day);
  }

  public clicarNoNo(stage: JourneyStage): void {
    this.selectStage.emit(stage);
  }
}
