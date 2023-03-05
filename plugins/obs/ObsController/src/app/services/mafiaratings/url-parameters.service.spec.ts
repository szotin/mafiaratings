import { TestBed } from '@angular/core/testing';

import { UrlParametersService } from './url-parameters.service';

describe('UrlParametersService', () => {
  let service: UrlParametersService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(UrlParametersService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
