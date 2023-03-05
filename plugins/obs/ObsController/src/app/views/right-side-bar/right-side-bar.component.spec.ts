import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ObsRightSideBarComponent } from './right-side-bar.component';

describe('ObsRightSideBarComponent', () => {
  let component: ObsRightSideBarComponent;
  let fixture: ComponentFixture<ObsRightSideBarComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [ ObsRightSideBarComponent ]
    })
    .compileComponents();

    fixture = TestBed.createComponent(ObsRightSideBarComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
