import { TestBed } from '@angular/core/testing';

import { ObsApiService } from './obs-api.service';

describe('ObsApiService', () => {
  let service: ObsApiService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(ObsApiService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
