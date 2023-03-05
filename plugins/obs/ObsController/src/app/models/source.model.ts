import { TransformData } from './transform-data.model'

export interface Source {
  sceneItemEnabled: boolean
  sceneItemId: number
  sourceName: string
  sceneItemTransform: TransformData
}
