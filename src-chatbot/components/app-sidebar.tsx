'use client';

// import type { User } from 'next-auth'; // Removed as next-auth is uninstalled
import { useRouter } from 'next/navigation';

import { PlusIcon } from '@/components/icons';
import { SidebarHistory } from '@/components/sidebar-history';
import { SidebarUserNav, type MockSessionUser } from '@/components/sidebar-user-nav'; // Import MockSessionUser
import { Button } from '@/components/ui/button';
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  useSidebar,
} from '@/components/ui/sidebar';
import Link from 'next/link';
import { Tooltip, TooltipContent, TooltipTrigger } from './ui/tooltip';
import { getConfig } from '@/lib/constants'; // Changed from 'config' to 'getConfig'
import React, { useState } from 'react'; // Import useState
// import { ChevronDownIcon, ChevronRightIcon } from '@/components/icons'; // Assuming these icons exist or will be added
import type { PARAItem } from '@/lib/window'; // Corrected: Import only PARAItem

// Placeholder ChevronDownIcon component
const ChevronDownIcon = ({ className }: { className?: string }) => (
  <svg
    className={className}
    xmlns="http://www.w3.org/2000/svg"
    width="24"
    height="24"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth="2"
    strokeLinecap="round"
    strokeLinejoin="round"
  >
    <polyline points="6 9 12 15 18 9" />
  </svg>
);

// Placeholder ChevronRightIcon component
const ChevronRightIcon = ({ className }: { className?: string }) => (
  <svg
    className={className}
    xmlns="http://www.w3.org/2000/svg"
    width="24"
    height="24"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth="2"
    strokeLinecap="round"
    strokeLinejoin="round"
  >
    <polyline points="9 18 15 12 9 6" />
  </svg>
);

// Placeholder FileIcon component
const FileIcon = ({ className }: { className?: string }) => (
  <svg
    className={className}
    xmlns="http://www.w3.org/2000/svg"
    width="24"
    height="24"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth="2"
    strokeLinecap="round"
    strokeLinejoin="round"
  >
    <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
    <polyline points="14 2 14 8 20 8" />
  </svg>
);

// Helper to get icon component (if we used string placeholders in config)
// For now, icons are directly in mock data in constants.ts for fallback,
// but real config from PHP won't have components.
// This function demonstrates how one might map string names to actual components.
const IconMap: Record<string, React.FC<{ className?: string }>> = {
  FileIcon: FileIcon,
  // Add other icons here if they were string-based in config
};

// CollapsibleSection component
interface CollapsibleSectionProps {
  title: string;
  children: React.ReactNode;
  defaultOpen?: boolean;
}

const CollapsibleSection: React.FC<CollapsibleSectionProps> = ({
  title,
  children,
  defaultOpen = false,
}) => {
  const [isOpen, setIsOpen] = useState(defaultOpen);

  return (
    <div className="mb-2">
      <Button
        variant="ghost"
        className="w-full justify-between h-8 px-2 text-xs font-semibold text-muted-foreground tracking-wider hover:bg-muted/50"
        onClick={() => setIsOpen(!isOpen)}
      >
        {title}
        {isOpen ? (
          <ChevronDownIcon className="h-4 w-4" />
        ) : (
          <ChevronRightIcon className="h-4 w-4" />
        )}
      </Button>
      {isOpen && <div className="mt-1 ml-2 space-y-1">{children}</div>}
    </div>
  );
};

export function AppSidebar({ user }: { user: MockSessionUser | undefined }) { // Use MockSessionUser
  const router = useRouter();
  const { setOpenMobile } = useSidebar();
  const currentConfig = getConfig(); // Call getConfig()

  const projects = currentConfig.projects || [];
  const starred = currentConfig.starred || [];
  return (
    <Sidebar className="group-data-[side=left]:border-r-0">
      <SidebarHeader>
        <SidebarMenu>
          <div className="flex flex-row justify-between items-center mb-2">
            <Link
              href={currentConfig.wp_admin_url} // Use currentConfig, provide fallback for href
              onClick={() => {
                setOpenMobile(false);
              }}
              className="flex flex-row gap-3 items-center"
            >
              <span className="text-lg font-semibold px-2 hover:bg-muted rounded-md cursor-pointer">
                {currentConfig.site_title}
              </span>
            </Link>
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  variant="ghost"
                  type="button"
                  className="p-2 h-fit"
                  onClick={() => {
                    setOpenMobile(false);
                    // Navigate to admin.php?page=personalos-chatbot without id parameter
                    if (typeof window !== 'undefined') {
                      window.location.href = 'admin.php?page=personalos-chatbot';
                    }
                  }}
                >
                  <PlusIcon />
                </Button>
              </TooltipTrigger>
              <TooltipContent align="end">New Chat</TooltipContent>
            </Tooltip>
          </div>
          <Button
            variant="ghost"
            className="w-full justify-start h-8 mb-2"
            asChild
          >
            <Link
              href={currentConfig.wp_admin_url || '#'}
              onClick={() => {
                setOpenMobile(false);
              }}
            >
              &larr; WP-Admin
            </Link>
          </Button>
        </SidebarMenu>
      </SidebarHeader>
      <SidebarContent>
        <CollapsibleSection title="Starred" defaultOpen> {/* Renamed from Areas */}
          <div className="space-y-1">
            {starred.map((item: PARAItem) => { // Renamed from areas to starred, item from area to item
               const IconComponent =
               typeof item.icon === 'string'
                 ? IconMap[item.icon]
                 : item.icon;
              return (
                <Button
                  key={item.id}
                  variant="ghost"
                  className="w-full justify-start h-8"
                  asChild
                >
                  <Link href={`#${item.id}`} className="flex items-center">
                    {IconComponent && (
                      <IconComponent className="mr-2 h-4 w-4 flex-shrink-0" />
                    )}
                    <span className="truncate">{item.name}</span>
                  </Link>
                </Button>
              );
            })}
          </div>
        </CollapsibleSection>

        <CollapsibleSection title="Projects">
          <div className="space-y-1">
            {projects.map((project: PARAItem) => {
              const IconComponent =
                typeof project.icon === 'string'
                  ? IconMap[project.icon]
                  : project.icon; // Assuming project.icon could be a component directly (fallback scenario)
              return (
                <Button
                  key={project.id}
                  variant="ghost"
                  className="w-full justify-start h-8"
                  asChild
                >
                  <Link href={`#${project.id}`} className="flex items-center">
                    {IconComponent && (
                      <IconComponent className="mr-2 h-4 w-4 flex-shrink-0" />
                    )}
                    <span className="truncate">{project.name}</span>
                  </Link>
                </Button>
              );
            })}
          </div>
        </CollapsibleSection>

        <CollapsibleSection title="All Chats" defaultOpen>
          <SidebarHistory user={user} />
        </CollapsibleSection>
      </SidebarContent>
      <SidebarFooter>{user && <SidebarUserNav user={user} />}</SidebarFooter>
    </Sidebar>
  );
}
