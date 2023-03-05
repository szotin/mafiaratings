import { Component, OnInit } from '@angular/core';
import { CommonService } from './services/common.service';

@Component({
  selector: 'obs-root',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.sass'],
})
export class ObsComponent implements OnInit{
  public isOpened = false;
  constructor(private commonService: CommonService) {}

  ngOnInit(): void {
    this.commonService.isPropVisible().subscribe({
      next: (data: boolean)=>{
        this.isOpened=data;
      }
    })
  }

  public closeProperties() {
    this.commonService.setIsPropVisible(false);
  }
}
