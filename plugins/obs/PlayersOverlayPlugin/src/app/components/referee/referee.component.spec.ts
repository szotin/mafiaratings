import { ComponentFixture, TestBed } from '@angular/core/testing';

import { RefereeComponent } from './referee.component';

describe('RefereeComponent', () => {
  let component: RefereeComponent;
  let fixture: ComponentFixture<RefereeComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [ RefereeComponent ]
    })
    .compileComponents();
  });

  beforeEach(() => {
    fixture = TestBed.createComponent(RefereeComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
