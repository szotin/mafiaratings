import { ComponentFixture, TestBed } from '@angular/core/testing';

import { GamestatsComponent } from './gamestats.component';

describe('GamestatsComponent', () => {
  let component: GamestatsComponent;
  let fixture: ComponentFixture<GamestatsComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [ GamestatsComponent ]
    })
    .compileComponents();
  });

  beforeEach(() => {
    fixture = TestBed.createComponent(GamestatsComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
