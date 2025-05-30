// import Form from 'next/form'; // Temporarily disabled for static export

// import { signOut } from '@/app/(auth)/auth'; // Removed as @/app/(auth)/auth.ts is deleted

export const SignOutForm = () => {
  // Corrected version for static export
  return (
    <form className="w-full">
      {/* Server action removed for static export */}
      <button
        type="submit"
        className="w-full text-left px-1 py-0.5 text-red-500"
        disabled // Disable button as action is removed
      >
        Sign out
      </button>
    </form>
  );
};
