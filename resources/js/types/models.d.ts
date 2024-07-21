export interface Baby {
  // columns
  id: number
  name: string
  birth_date: string
  user_id: number
  created_at: string|null
  updated_at: string|null
  // relations
  baby_actions: BabyAction[]
}

export interface BabyAction {
  // columns
  id: number
  baby_action_type_id: number
  started_at: string
  finished_at: string|null
  baby_id: number
  created_at: string|null
  updated_at: string|null
  // relations
  baby: Baby
  baby_action_type: BabyActionType
}

export interface User {
  // columns
  id: number
  name: string
  email: string
  email_verified_at: string|null
  password?: string
  remember_token?: string|null
  created_at: string|null
  updated_at: string|null
  // relations
  babies: Baby[]
}

export interface BabyActionType {
    id: number,
    name: string
}

