import { Component } from '@angular/core';
import { Quizz } from "../../components/quizz/quizz";

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [Quizz],
  templateUrl: './home.html',
  styleUrl: './home.css',
})
export class Home {}
