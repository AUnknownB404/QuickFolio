import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface Portfolio {
    id: number;
    title: string;
    slug: string;
    status: 'draft' | 'published';
    template_id: number | null;
    template?: {
        id: number;
        name: string;
    } | null;
    published_at?: string | null;
    created_at: string;
    updated_at: string;
}

export interface ProjectPortfolio {
    id: number;
    title: string;
}

export interface ProjectSkill {
    id: number;
    name: string;
}

export interface Project {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    image: string | null;
    project_url: string | null;
    github_url: string | null;
    start_date: string | null;
    end_date: string | null;
    is_featured: boolean;
    sort_order: number;
    skills: ProjectSkill[];
}

export interface PortfolioSkill {
    id: number;
    name: string;
    slug: string;
    level: number | null;
    sort_order: number;
}

export interface Experience {
    id: number;
    company: string;
    position: string;
    description: string | null;
    location: string | null;
    start_date: string | null;
    end_date: string | null;
    is_current: boolean;
    sort_order: number;
}

export interface Education {
    id: number;
    institution: string;
    degree: string;
    field_of_study: string | null;
    description: string | null;
    start_date: string | null;
    end_date: string | null;
    sort_order: number;
}

export interface Certification {
    id: number;
    name: string;
    organization: string;
    credential_id: string | null;
    credential_url: string | null;
    issue_date: string | null;
    expiry_date: string | null;
    image: string | null;
}

export interface SocialLink {
    id: number;
    platform: string;
    url: string;
    sort_order: number;
}

export type BreadcrumbItemType = BreadcrumbItem;
