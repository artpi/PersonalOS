// 'use server'; // Disabled for static export

import { z } from 'zod';

// import { createUser, getUser } from '@/lib/db/queries'; // Removed as DB is disabled

// import { signIn } from './auth'; // Removed as ./auth.ts is deleted

const authFormSchema = z.object({
  email: z.string().email(),
  password: z.string().min(6),
});

export interface LoginActionState {
  status: 'idle' | 'in_progress' | 'success' | 'failed' | 'invalid_data';
}

export const login = async (
  _: LoginActionState,
  formData: FormData,
): Promise<LoginActionState> => {
  try {
    const validatedData = authFormSchema.parse({
      email: formData.get('email'),
      password: formData.get('password'),
    });

    // await signIn('credentials', { // Removed as ./auth.ts is deleted
    //   email: validatedData.email,
    //   password: validatedData.password,
    //   redirect: false,
    // });
    console.warn('signIn call in login() disabled as auth system is removed.');

    return { status: 'success' };
  } catch (error) {
    if (error instanceof z.ZodError) {
      return { status: 'invalid_data' };
    }

    return { status: 'failed' };
  }
};

export interface RegisterActionState {
  status:
    | 'idle'
    | 'in_progress'
    | 'success'
    | 'failed'
    | 'user_exists'
    | 'invalid_data';
}

export const register = async (
  _: RegisterActionState,
  formData: FormData,
): Promise<RegisterActionState> => {
  try {
    const validatedData = authFormSchema.parse({
      email: formData.get('email'),
      password: formData.get('password'),
    });

    // const [user] = await getUser(validatedData.email); // DB call disabled for static export
    // if (user) { // Logic dependent on DB call disabled for static export
    //   return { status: 'user_exists' } as RegisterActionState;
    // }
    // await createUser(validatedData.email, validatedData.password); // DB call disabled for static export
    console.warn('User check & creation in register() disabled for static export');

    // await signIn('credentials', { // Removed as ./auth.ts is deleted
    //   email: validatedData.email,
    //   password: validatedData.password,
    //   redirect: false,
    // });
    console.warn('signIn call in register() disabled as auth system is removed.');

    return { status: 'success' };
  } catch (error) {
    if (error instanceof z.ZodError) {
      return { status: 'invalid_data' };
    }

    return { status: 'failed' };
  }
};
