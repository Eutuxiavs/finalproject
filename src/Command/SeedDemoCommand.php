<?php

namespace App\Command;

use App\Entity\Conversation;
use App\Entity\Item;
use App\Entity\Message;
use App\Entity\Offer;
use App\Entity\Report;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed-demo', description: 'Create (or reset) the demo admin, staff and client accounts with sample data')]
class SeedDemoCommand extends Command
{
    public const ADMIN_EMAIL = 'admin@yonzon.test';
    public const STAFF_EMAIL = 'staff@yonzon.test';

    private const CLIENTS = [
        ['maria.santos@yonzon.test', 'Maria Santos', 'Santos Camera Repair', 'Iloilo City'],
        ['john.reyes@yonzon.test', 'John Reyes', 'Reyes Cycles', 'Jaro, Iloilo'],
        ['ana.cruz@yonzon.test', 'Ana Cruz', null, 'Mandurriao, Iloilo'],
        ['carlo.mendoza@yonzon.test', 'Carlo Mendoza', 'Mendoza Audio', 'Molo, Iloilo'],
        ['liza.tan@yonzon.test', 'Liza Tan', null, 'La Paz, Iloilo'],
    ];

    private const ITEMS = [
        // owner index => [name, category, condition, status, price, serial]
        0 => [
            ['Canon AE-1 Film Camera', 'Photography', 'Good', 'for_sale', '8500.00', 'CAN-AE1-20318'],
            ['Fujifilm X-T30 Body', 'Photography', 'Like new', 'for_sale', '42000.00', 'FUJ-XT30-77120'],
            ['Tripod (carbon)', 'Photography', 'Good', 'owned', null, null],
        ],
        1 => [
            ['Trek Marlin 7 Mountain Bike', 'Cycling', 'Good', 'for_sale', '31000.00', 'TRK-M7-55812'],
            ['Cycling Helmet (M)', 'Cycling', 'Like new', 'owned', null, null],
            ['Cannondale Road Bike', 'Cycling', 'Good', 'lost', null, 'CND-RB-90021'],
        ],
        2 => [
            ['Seiko 5 Automatic Watch', 'Timepieces', 'Good', 'for_sale', '9800.00', 'SEI-5-44109'],
            ['Kindle Paperwhite', 'Electronics', 'Like new', 'owned', null, 'KND-PW-31877'],
        ],
        3 => [
            ['Yamaha FG800 Acoustic Guitar', 'Instruments', 'Good', 'for_sale', '14500.00', 'YAM-FG8-10442'],
            ['Behringer Mixer', 'Electronics', 'Fair', 'owned', null, null],
        ],
        4 => [
            ['Nike Air Force 1 (US 8)', 'Footwear', 'Like new', 'for_sale', '4200.00', null],
            ['Wallet (brown leather)', 'Fashion', 'Good', 'lost', null, null],
        ],
    ];

    public function __construct(
        private UserRepository $users,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('admin-only', null, InputOption::VALUE_NONE, 'Only create or reset the admin and staff accounts (no clients or sample data)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'dev') === 'prod') {
            $output->writeln('<error>Refusing to create demo accounts in production.</error>');

            return Command::FAILURE;
        }

        $rows = [];

        // Passwords are never stored in the code. Set SEED_ADMIN_PASSWORD, SEED_STAFF_PASSWORD and
        // SEED_CLIENT_PASSWORD in .env.local to choose your own, otherwise random ones are generated
        // and shown once in this terminal.
        $adminPassword = $this->password('SEED_ADMIN_PASSWORD');
        $staffPassword = $this->password('SEED_STAFF_PASSWORD');
        $clientPassword = $this->password('SEED_CLIENT_PASSWORD');

        $this->upsert(self::ADMIN_EMAIL, 'Site Admin', null, null, $adminPassword, ['ROLE_ADMIN']);
        $rows[] = ['Admin', self::ADMIN_EMAIL, $adminPassword];

        $this->upsert(self::STAFF_EMAIL, 'Sam Staff', null, null, $staffPassword, ['ROLE_STAFF']);
        $rows[] = ['Staff', self::STAFF_EMAIL, $staffPassword];

        if (!$input->getOption('admin-only')) {
            $clients = [];
            foreach (self::CLIENTS as [$email, $name, $business, $city]) {
                $clients[] = $this->upsert($email, $name, $business, $city, $clientPassword, []);
                $rows[] = ['Client', $email, $clientPassword];
            }
            $this->em->flush();

            $this->seedItems($clients);
            $this->seedActivity($clients);
        }

        $this->em->flush();

        $output->writeln('');
        $output->writeln('<info>Demo accounts are ready. Log in with the email as the username.</info>');
        $output->writeln('<info>Passwords are shown only now. Run this command again for new ones.</info>');
        (new Table($output))->setHeaders(['Role', 'Username (email)', 'Password'])->setRows($rows)->render();
        $output->writeln('<comment>Demo accounts only. Delete them before any real deployment.</comment>');

        return Command::SUCCESS;
    }

    /** A password from the environment (.env.local) if set, otherwise a fresh random one. */
    private function password(string $envName): string
    {
        $value = $_SERVER[$envName] ?? $_ENV[$envName] ?? getenv($envName);
        if (is_string($value) && strlen($value) >= 8) {
            return $value;
        }

        return substr(strtr(base64_encode(random_bytes(12)), '+/=', 'xyz'), 0, 14);
    }

    private function upsert(string $email, string $name, ?string $business, ?string $city, string $password, array $roles): User
    {
        $user = $this->users->findOneBy(['email' => $email]) ?? new User();
        $user->setEmail($email);
        $user->setFullName($name);
        $user->setBusiness($business);
        $user->setRoles($roles);
        $user->setIsSuspended(false);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);

        return $user;
    }

    /** @param User[] $clients */
    private function seedItems(array $clients): void
    {
        foreach (self::ITEMS as $ownerIndex => $items) {
            $owner = $clients[$ownerIndex];
            if ($this->em->getRepository(Item::class)->count(['owner' => $owner]) > 0) {
                continue;
            }

            foreach ($items as [$name, $category, $condition, $status, $price, $serial]) {
                $item = new Item();
                $item->name = $name;
                $item->category = $category;
                $item->itemCondition = $condition;
                $item->status = $status;
                $item->price = $price;
                $item->serialNumber = $serial;
                $item->city = 'Iloilo';
                $item->owner = $owner;
                $this->em->persist($item);
                $this->em->flush();

                $letters = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $category), 0, 4));
                $item->claimId = sprintf('YZ-%s-%06d', $letters, $item->id);
            }
        }
        $this->em->flush();
    }

    /** @param User[] $clients */
    private function seedActivity(array $clients): void
    {
        if ($this->em->getRepository(Offer::class)->count([]) > 0) {
            return;
        }

        $this->seedReport($clients);

        $camera = $this->em->getRepository(Item::class)->findOneBy(['owner' => $clients[0], 'status' => Item::STATUS_FOR_SALE], ['id' => 'ASC']);
        if (!$camera) {
            return;
        }

        $offer = new Offer();
        $offer->item = $camera;
        $offer->seller = $clients[0];
        $offer->buyer = $clients[1];
        $offer->amount = '8000.00';
        $offer->message = 'Hi! Would you take 8,000 if I pick it up this weekend?';
        $this->em->persist($offer);

        $conversation = new Conversation();
        $conversation->item = $camera;
        $conversation->seller = $clients[0];
        $conversation->buyer = $clients[1];
        $this->em->persist($conversation);
        $this->em->flush();

        foreach ([[$clients[1], 'Hello, is the Canon AE-1 still available?'], [$clients[0], 'Yes, it is. It comes with the original strap.']] as [$sender, $body]) {
            $message = new Message();
            $message->conversation = $conversation;
            $message->sender = $sender;
            $message->body = $body;
            $this->em->persist($message);
        }
    }

    /** One open report so the staff report queue has something to show. */
    private function seedReport(array $clients): void
    {
        $item = $this->em->getRepository(Item::class)->findOneBy(['owner' => $clients[4], 'status' => Item::STATUS_FOR_SALE]);
        if (!$item) {
            return;
        }

        $report = new Report();
        $report->reporter = $clients[1];
        $report->reportedUser = $clients[4];
        $report->reportedItem = $item;
        $report->itemLabel = $item->name;
        $report->reason = 'The listing has no photo of the actual shoes, so I cannot tell if they are real.';
        $this->em->persist($report);
    }
}