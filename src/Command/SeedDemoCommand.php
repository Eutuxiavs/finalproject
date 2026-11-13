<?php

namespace App\Command;

use App\Entity\Conversation;
use App\Entity\Item;
use App\Entity\Message;
use App\Entity\Offer;
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

#[AsCommand(name: 'app:seed-demo', description: 'Create (or reset) the demo admin and client accounts with sample data')]
class SeedDemoCommand extends Command
{
    public const ADMIN_EMAIL = 'admin@yonzon.test';
    public const ADMIN_PASSWORD = 'Admin@12345';
    public const CLIENT_PASSWORD = 'Client@12345';

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
        $this->addOption('admin-only', null, InputOption::VALUE_NONE, 'Only create or reset the admin account (no clients or sample data)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = [];

        $admin = $this->upsert(self::ADMIN_EMAIL, 'Site Admin', null, null, self::ADMIN_PASSWORD, ['ROLE_ADMIN']);
        $rows[] = ['Admin', self::ADMIN_EMAIL, self::ADMIN_PASSWORD];

        if (!$input->getOption('admin-only')) {
            $clients = [];
            foreach (self::CLIENTS as [$email, $name, $business, $city]) {
                $clients[] = $this->upsert($email, $name, $business, $city, self::CLIENT_PASSWORD, []);
                $rows[] = ['Client', $email, self::CLIENT_PASSWORD];
            }
            $this->em->flush();

            $this->seedItems($clients);
            $this->seedActivity($clients);
        }

        $this->em->flush();

        $output->writeln('');
        $output->writeln('<info>Demo accounts are ready. Log in with the email as the username:</info>');
        (new Table($output))->setHeaders(['Role', 'Username (email)', 'Password'])->setRows($rows)->render();
        $output->writeln('<comment>These are demo credentials. Change or delete them before any real deployment.</comment>');

        return Command::SUCCESS;
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
}
